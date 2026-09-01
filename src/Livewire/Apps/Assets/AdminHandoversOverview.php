<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\HandoverRejectionAdminResolutionService;
use Hwkdo\IntranetAppAssets\Services\OpenHandoverAdminResolutionService;
use Hwkdo\IntranetAppAssets\Support\BulkAdminWorkflowSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Übergaben')]
class AdminHandoversOverview extends Component
{
    use WithPagination;

    public const string FILTER_OPEN = 'open';

    public const string FILTER_REJECTED = 'rejected';

    /** @var 'open'|'rejected' */
    #[Url(as: 'filter')]
    public string $filter = self::FILTER_OPEN;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var list<int> */
    public array $selectedHandoverIds = [];

    public bool $selectPage = false;

    public string $resolution = OpenHandoverAdminResolutionService::ResolutionNewOwner;

    public ?string $newOwnerUserId = null;

    public string $location = '';

    public string $bulkReason = '';

    private const string SESSION_OPEN = 'intranet_app_assets.bulk.open_handovers.selection';

    private const string SESSION_REJECTED = 'intranet_app_assets.bulk.rejected_handovers.selection';

    public function mount(): void
    {
        $this->normalizeFilter();
        $this->syncResolutionDefault();
        $this->loadSelectionFromSession();
        $this->pruneStaleSelection();
    }

    public function updatedFilter(): void
    {
        $this->normalizeFilter();
        Session::forget([self::SESSION_OPEN, self::SESSION_REJECTED]);
        $this->selectedHandoverIds = [];
        $this->selectPage = false;
        $this->newOwnerUserId = null;
        $this->location = '';
        $this->bulkReason = '';
        $this->syncResolutionDefault();
        $this->resetPage();
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    private function normalizeFilter(): void
    {
        $f = trim($this->filter);
        $this->filter = $f === self::FILTER_REJECTED ? self::FILTER_REJECTED : self::FILTER_OPEN;
    }

    private function syncResolutionDefault(): void
    {
        if ($this->filter === self::FILTER_REJECTED) {
            $this->resolution = HandoverRejectionAdminResolutionService::ResolutionNewOwner;
        } else {
            $this->resolution = OpenHandoverAdminResolutionService::ResolutionNewOwner;
        }
    }

    private function sessionKey(): string
    {
        return $this->filter === self::FILTER_REJECTED ? self::SESSION_REJECTED : self::SESSION_OPEN;
    }

    private function loadSelectionFromSession(): void
    {
        $stored = Session::get($this->sessionKey(), []);
        $this->selectedHandoverIds = $this->sanitizeIds(is_array($stored) ? $stored : []);
    }

    public function updatedSelectedHandoverIds(): void
    {
        $this->selectedHandoverIds = $this->sanitizeIds($this->selectedHandoverIds);
        Session::put($this->sessionKey(), $this->selectedHandoverIds);
        $this->selectPage = $this->areAllCurrentPageSelected();
        $this->skipRender();
    }

    public function clearSelection(): void
    {
        $this->selectedHandoverIds = [];
        $this->selectPage = false;
        Session::forget($this->sessionKey());
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
        $this->skipRender();
    }

    public function submitBulkResolve(): void
    {
        $this->authorize('manage-app-assets');

        if ($this->filter === self::FILTER_REJECTED) {
            $this->submitBulkRejected();
        } else {
            $this->submitBulkOpen();
        }
    }

    private function submitBulkOpen(): void
    {
        $rules = [
            'selectedHandoverIds' => ['required', 'array', 'min:1'],
            'selectedHandoverIds.*' => ['integer', 'min:1'],
            'resolution' => ['required', 'string', 'in:'.OpenHandoverAdminResolutionService::ResolutionNewOwner.','.OpenHandoverAdminResolutionService::ResolutionSetLocation.','.OpenHandoverAdminResolutionService::ResolutionMarkMissing],
            'bulkReason' => ['required', 'string', 'min:3', 'max:5000'],
        ];
        if ($this->resolution === OpenHandoverAdminResolutionService::ResolutionNewOwner) {
            $rules['newOwnerUserId'] = ['required', 'exists:users,id'];
        }
        if ($this->resolution === OpenHandoverAdminResolutionService::ResolutionSetLocation) {
            $rules['location'] = ['required', 'string', 'min:1', 'max:255'];
        }
        $this->validate($rules);

        BulkAdminWorkflowSession::put(
            BulkAdminWorkflowSession::FLOW_OPEN_HANDOVER,
            $this->selectedHandoverIds,
            [
                'resolution' => $this->resolution,
                'new_owner_user_id' => $this->resolution === OpenHandoverAdminResolutionService::ResolutionNewOwner ? (int) $this->newOwnerUserId : null,
                'location' => $this->resolution === OpenHandoverAdminResolutionService::ResolutionSetLocation ? trim($this->location) : null,
                'bulk_reason' => trim($this->bulkReason),
            ],
        );

        $this->redirect(route('apps.assets.admin.bulk.review'), navigate: true);
    }

    private function submitBulkRejected(): void
    {
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

    /**
     * @return LengthAwarePaginator<int, Handover>
     */
    #[Computed]
    public function handovers(): LengthAwarePaginator
    {
        if ($this->filter === self::FILTER_REJECTED) {
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
                ->when($this->search !== '', function ($query): void {
                    $term = '%'.$this->search.'%';
                    $query->where(function ($q) use ($term): void {
                        $q->whereHas('asset', function ($aq) use ($term): void {
                            $aq->where('serial_number', 'like', $term)
                                ->orWhere('model', 'like', $term)
                                ->orWhere('name', 'like', $term)
                                ->orWhere('itexia_id', 'like', $term);
                        })
                            ->orWhereHas('recipient', fn ($rq) => $rq->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term))
                            ->orWhereHas('issuer', fn ($iq) => $iq->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term));
                    });
                })
                ->orderByDesc('rejected_at')
                ->paginate(25);
        }

        return Handover::query()
            ->open()
            ->with([
                'asset.type',
                'asset.vendor',
                'recipient',
                'issuer',
            ])
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term): void {
                    $q->whereHas('asset', function ($aq) use ($term): void {
                        $aq->where('serial_number', 'like', $term)
                            ->orWhere('model', 'like', $term)
                            ->orWhere('name', 'like', $term)
                            ->orWhere('itexia_id', 'like', $term);
                    })
                        ->orWhereHas('recipient', fn ($rq) => $rq->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term))
                        ->orWhereHas('issuer', fn ($iq) => $iq->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('intranet-app-assets::livewire.apps.assets.admin-handovers-overview', [
            'handovers' => $this->handovers,
            'users' => User::query()->orderBy('nachname')->orderBy('vorname')->get(),
        ]);
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
        return $this->handovers->getCollection()
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

        if ($this->filter === self::FILTER_REJECTED) {
            $valid = Handover::query()
                ->whereIn('id', $this->selectedHandoverIds)
                ->rejectedPendingAdmin()
                ->pluck('id')
                ->map(static fn (int|string $id): int => (int) $id)
                ->all();
        } else {
            $valid = Handover::query()
                ->whereIn('id', $this->selectedHandoverIds)
                ->open()
                ->pluck('id')
                ->map(static fn (int|string $id): int => (int) $id)
                ->all();
        }

        $pruned = array_values(array_intersect($this->selectedHandoverIds, $valid));
        if ($pruned !== $this->selectedHandoverIds) {
            $this->selectedHandoverIds = $pruned;
            Session::put($this->sessionKey(), $this->selectedHandoverIds);
            $this->selectPage = $this->areAllCurrentPageSelected();
        }
    }
}
