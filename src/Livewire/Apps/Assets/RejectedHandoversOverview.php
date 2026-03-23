<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\HandoverRejectionAdminResolutionService;
use Hwkdo\IntranetAppAssets\Support\BulkAdminWorkflowSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Abgelehnte Übergaben')]
class RejectedHandoversOverview extends Component
{
    use WithPagination;

    /** @var list<int> */
    public array $selectedHandoverIds = [];

    public bool $selectPage = false;

    public string $resolution = HandoverRejectionAdminResolutionService::ResolutionNewOwner;

    public ?string $newOwnerUserId = null;

    public string $location = '';

    public string $bulkReason = '';

    private const SELECTION_SESSION_KEY = 'intranet_app_assets.bulk.rejected_handovers.selection';

    public function mount(): void
    {
        $stored = Session::get(self::SELECTION_SESSION_KEY, []);
        $this->selectedHandoverIds = $this->sanitizeIds(is_array($stored) ? $stored : []);
        $this->pruneStaleSelection();
    }

    public function updatedSelectedHandoverIds(): void
    {
        $this->selectedHandoverIds = $this->sanitizeIds($this->selectedHandoverIds);
        Session::put(self::SELECTION_SESSION_KEY, $this->selectedHandoverIds);
        $this->selectPage = $this->areAllCurrentPageSelected();
        $this->skipRender();
    }

    public function clearSelection(): void
    {
        $this->selectedHandoverIds = [];
        $this->selectPage = false;
        Session::forget(self::SELECTION_SESSION_KEY);
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
        $this->skipRender();
    }

    public function submitBulkResolve(): void
    {
        $this->authorize('manage-app-assets');

        $rules = [
            'selectedHandoverIds' => ['required', 'array', 'min:1'],
            'selectedHandoverIds.*' => ['integer', 'min:1'],
            'resolution' => ['required', 'string', 'in:'.HandoverRejectionAdminResolutionService::ResolutionNewOwner.','.HandoverRejectionAdminResolutionService::ResolutionSetLocation.','.HandoverRejectionAdminResolutionService::ResolutionMarkMissing],
            'bulkReason' => ['required', 'string', 'min:3', 'max:5000'],
        ];

        if ($this->resolution === HandoverRejectionAdminResolutionService::ResolutionNewOwner) {
            $rules['newOwnerUserId'] = ['required', 'exists:users,id'];
        }
        if ($this->resolution === HandoverRejectionAdminResolutionService::ResolutionSetLocation) {
            $rules['location'] = ['required', 'string', 'min:1', 'max:255'];
        }

        $this->validate($rules);

        BulkAdminWorkflowSession::put(
            BulkAdminWorkflowSession::FLOW_REJECTED_HANDOVER,
            $this->selectedHandoverIds,
            [
                'resolution' => $this->resolution,
                'new_owner_user_id' => $this->resolution === HandoverRejectionAdminResolutionService::ResolutionNewOwner ? (int) $this->newOwnerUserId : null,
                'location' => $this->resolution === HandoverRejectionAdminResolutionService::ResolutionSetLocation ? trim($this->location) : null,
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

        return view('intranet-app-assets::livewire.apps.assets.rejected-handovers-overview', [
            'handovers' => $this->handovers(),
            'users' => $users,
        ]);
    }

    /**
     * @return LengthAwarePaginator<Handover>
     */
    protected function handovers(): LengthAwarePaginator
    {
        return Handover::query()
            ->rejectedPendingAdmin()
            ->with([
                'asset.type',
                'asset.vendor',
                'recipient',
                'issuer',
                'rejectedBy',
                'notes' => fn ($q) => $q->latest('intranet_app_assets_asset_notes.id'),
            ])
            ->orderByDesc('rejected_at')
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
    private function currentPageHandoverIds(): array
    {
        return $this->handovers()
            ->getCollection()
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    private function areAllCurrentPageSelected(): bool
    {
        $currentIds = $this->currentPageHandoverIds();
        if ($currentIds === []) {
            return false;
        }

        return count(array_diff($currentIds, $this->selectedHandoverIds)) === 0;
    }

    private function pruneStaleSelection(): void
    {
        if ($this->selectedHandoverIds === []) {
            return;
        }

        $valid = Handover::query()
            ->whereIn('id', $this->selectedHandoverIds)
            ->rejectedPendingAdmin()
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        $pruned = array_values(array_intersect($this->selectedHandoverIds, $valid));
        if ($pruned !== $this->selectedHandoverIds) {
            $this->selectedHandoverIds = $pruned;
            Session::put(self::SELECTION_SESSION_KEY, $this->selectedHandoverIds);
            $this->selectPage = $this->areAllCurrentPageSelected();
        }
    }
}
