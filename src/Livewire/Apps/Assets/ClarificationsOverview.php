<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Services\AssetClarificationAdminResolutionService;
use Hwkdo\IntranetAppAssets\Support\AssetUnownedDeviceType;
use Hwkdo\IntranetAppAssets\Support\BulkAdminWorkflowSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Assets in Klärung')]
class ClarificationsOverview extends Component
{
    use WithPagination;

    /** @var list<int> */
    public array $selectedAssetIds = [];

    public bool $selectPage = false;

    public string $resolution = AssetClarificationAdminResolutionService::ResolutionClearOnly;

    public ?string $newOwnerUserId = null;

    public string $location = '';

    public string $deviceType = AssetUnownedDeviceType::Pool;

    public string $bulkReason = '';

    private const SELECTION_SESSION_KEY = 'intranet_app_assets.bulk.clarifications.selection';

    public function mount(): void
    {
        $stored = Session::get(self::SELECTION_SESSION_KEY, []);
        $this->selectedAssetIds = $this->sanitizeIds(is_array($stored) ? $stored : []);
        $this->pruneStaleSelection();
    }

    public function updatedSelectedAssetIds(): void
    {
        $this->selectedAssetIds = $this->sanitizeIds($this->selectedAssetIds);
        Session::put(self::SELECTION_SESSION_KEY, $this->selectedAssetIds);
        $this->selectPage = $this->areAllCurrentPageSelected();
        $this->skipRender();
    }

    public function clearSelection(): void
    {
        $this->selectedAssetIds = [];
        $this->selectPage = false;
        Session::forget(self::SELECTION_SESSION_KEY);
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
        $this->skipRender();
    }

    public function submitBulkResolve(): void
    {
        $this->authorize('manage-app-assets');

        $rules = [
            'selectedAssetIds' => ['required', 'array', 'min:1'],
            'selectedAssetIds.*' => ['integer', 'min:1'],
            'resolution' => ['required', 'string', 'in:'.AssetClarificationAdminResolutionService::ResolutionClearOnly.','.AssetClarificationAdminResolutionService::ResolutionNewOwner.','.AssetClarificationAdminResolutionService::ResolutionSetLocation.','.AssetClarificationAdminResolutionService::ResolutionMarkMissing],
            'bulkReason' => ['required', 'string', 'min:3', 'max:5000'],
        ];

        if ($this->resolution === AssetClarificationAdminResolutionService::ResolutionNewOwner) {
            $rules['newOwnerUserId'] = ['required', 'exists:users,id'];
        }
        if ($this->resolution === AssetClarificationAdminResolutionService::ResolutionSetLocation) {
            $rules['location'] = ['required', 'string', 'min:1', 'max:255'];
            $rules['deviceType'] = ['required', 'string', Rule::in(AssetUnownedDeviceType::values())];
        }

        $this->validate($rules);

        BulkAdminWorkflowSession::put(
            BulkAdminWorkflowSession::FLOW_CLARIFICATION,
            $this->selectedAssetIds,
            [
                'resolution' => $this->resolution,
                'new_owner_user_id' => $this->resolution === AssetClarificationAdminResolutionService::ResolutionNewOwner ? (int) $this->newOwnerUserId : null,
                'location' => $this->resolution === AssetClarificationAdminResolutionService::ResolutionSetLocation ? trim($this->location) : null,
                'mark_in_stock' => $this->resolution === AssetClarificationAdminResolutionService::ResolutionSetLocation
                    ? AssetUnownedDeviceType::toIsInStock($this->deviceType)
                    : null,
                'bulk_reason' => trim($this->bulkReason),
            ],
        );

        $this->redirect(route('apps.assets.admin.bulk.review'), navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $users = \App\Models\User::query()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        return view('intranet-app-assets::livewire.apps.assets.clarifications-overview', [
            'assets' => $this->assets(),
            'users' => $users,
        ]);
    }

    /**
     * @return LengthAwarePaginator<Asset>
     */
    protected function assets(): LengthAwarePaginator
    {
        return Asset::query()
            ->where('is_clarification', true)
            ->with(['type', 'vendor', 'owner.standort'])
            ->orderByDesc('updated_at')
            ->paginate(25);
    }

    /**
     * @param  array<int|string>  $ids
     * @return list<int>
     */
    private function sanitizeIds(array $ids): array
    {
        $normalized = array_map(static fn ($id): int => (int) $id, $ids);
        $normalized = array_filter($normalized, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($normalized));
    }

    /**
     * @return list<int>
     */
    private function currentPageAssetIds(): array
    {
        return $this->assets()
            ->getCollection()
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    private function areAllCurrentPageSelected(): bool
    {
        $currentIds = $this->currentPageAssetIds();
        if ($currentIds === []) {
            return false;
        }

        return count(array_diff($currentIds, $this->selectedAssetIds)) === 0;
    }

    private function pruneStaleSelection(): void
    {
        if ($this->selectedAssetIds === []) {
            return;
        }

        $valid = Asset::query()
            ->whereIn('id', $this->selectedAssetIds)
            ->where('is_clarification', true)
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        $pruned = array_values(array_intersect($this->selectedAssetIds, $valid));
        if ($pruned !== $this->selectedAssetIds) {
            $this->selectedAssetIds = $pruned;
            Session::put(self::SELECTION_SESSION_KEY, $this->selectedAssetIds);
            $this->selectPage = $this->areAllCurrentPageSelected();
        }
    }
}
