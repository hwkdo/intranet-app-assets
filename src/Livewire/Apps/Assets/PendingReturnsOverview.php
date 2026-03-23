<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Services\AssetReturnAdminCompletionService;
use Hwkdo\IntranetAppAssets\Support\BulkAdminWorkflowSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Offene Rückgaben')]
class PendingReturnsOverview extends Component
{
    use WithPagination;

    /** @var list<int> */
    public array $selectedReturnIds = [];

    public bool $selectPage = false;

    public string $resolution = AssetReturnAdminCompletionService::ResolutionNewOwner;

    public ?string $newOwnerUserId = null;

    public string $location = '';

    public string $bulkReason = '';

    private const SELECTION_SESSION_KEY = 'intranet_app_assets.bulk.pending_returns.selection';

    public function mount(): void
    {
        $stored = Session::get(self::SELECTION_SESSION_KEY, []);
        $this->selectedReturnIds = $this->sanitizeIds(is_array($stored) ? $stored : []);
        $this->pruneStaleSelection();
    }

    public function updatedSelectedReturnIds(): void
    {
        $this->selectedReturnIds = $this->sanitizeIds($this->selectedReturnIds);
        Session::put(self::SELECTION_SESSION_KEY, $this->selectedReturnIds);
        $this->selectPage = $this->areAllCurrentPageSelected();
        $this->skipRender();
    }

    public function clearSelection(): void
    {
        $this->selectedReturnIds = [];
        $this->selectPage = false;
        Session::forget(self::SELECTION_SESSION_KEY);
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
        $this->skipRender();
    }

    public function submitBulkResolve(): void
    {
        $this->authorize('manage-app-assets');

        $rules = [
            'selectedReturnIds' => ['required', 'array', 'min:1'],
            'selectedReturnIds.*' => ['integer', 'min:1'],
            'resolution' => ['required', 'string', 'in:'.AssetReturnAdminCompletionService::ResolutionNewOwner.','.AssetReturnAdminCompletionService::ResolutionSetLocation],
            'bulkReason' => ['required', 'string', 'min:3', 'max:5000'],
        ];

        if ($this->resolution === AssetReturnAdminCompletionService::ResolutionNewOwner) {
            $rules['newOwnerUserId'] = ['required', 'exists:users,id'];
        }

        if ($this->resolution === AssetReturnAdminCompletionService::ResolutionSetLocation) {
            $rules['location'] = ['required', 'string', 'min:1', 'max:255'];
        }

        $this->validate($rules);

        BulkAdminWorkflowSession::put(
            BulkAdminWorkflowSession::FLOW_RETURN_COMPLETE,
            $this->selectedReturnIds,
            [
                'resolution' => $this->resolution,
                'new_owner_user_id' => $this->resolution === AssetReturnAdminCompletionService::ResolutionNewOwner ? (int) $this->newOwnerUserId : null,
                'location' => $this->resolution === AssetReturnAdminCompletionService::ResolutionSetLocation ? trim($this->location) : null,
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

        return view('intranet-app-assets::livewire.apps.assets.pending-returns-overview', [
            'returns' => $this->returns(),
            'users' => $users,
        ]);
    }

    /**
     * @return LengthAwarePaginator<AssetReturn>
     */
    protected function returns(): LengthAwarePaginator
    {
        return AssetReturn::query()
            ->whereNull('completed_at')
            ->whereHas('handover')
            ->with([
                'handover.asset.type',
                'handover.asset.vendor',
                'handover.recipient',
                'initiatedBy',
            ])
            ->orderByDesc('created_at')
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
    private function currentPageReturnIds(): array
    {
        return $this->returns()
            ->getCollection()
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    private function areAllCurrentPageSelected(): bool
    {
        $currentIds = $this->currentPageReturnIds();
        if ($currentIds === []) {
            return false;
        }

        return count(array_diff($currentIds, $this->selectedReturnIds)) === 0;
    }

    private function pruneStaleSelection(): void
    {
        if ($this->selectedReturnIds === []) {
            return;
        }

        $valid = AssetReturn::query()
            ->whereIn('id', $this->selectedReturnIds)
            ->whereNull('completed_at')
            ->whereHas('handover')
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        $pruned = array_values(array_intersect($this->selectedReturnIds, $valid));
        if ($pruned !== $this->selectedReturnIds) {
            $this->selectedReturnIds = $pruned;
            Session::put(self::SELECTION_SESSION_KEY, $this->selectedReturnIds);
            $this->selectPage = $this->areAllCurrentPageSelected();
        }
    }
}
