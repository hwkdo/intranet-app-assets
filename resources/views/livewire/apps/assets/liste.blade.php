<?php

use Hwkdo\IntranetAppAssets\Exports\AssetsTableExport;
use Hwkdo\IntranetAppAssets\Enums\ReturnScheduleType;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Services\AssetAdminMarkClarificationService;
use Hwkdo\IntranetAppAssets\Services\AssetLocationDisplayResolver;
use Hwkdo\IntranetAppAssets\Support\AssetListeAdminActions;
use Hwkdo\IntranetAppAssets\Support\BulkAdminWorkflowSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')] #[Title('Alle Assets')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var list<int|string> */
    #[Url(as: 'types')]
    public array $typeIds = [];

    #[Url]
    public string $statusFilter = '';

    /** @var 'model'|'created_at' */
    #[Url(as: 'sort')]
    public string $sortColumn = 'model';

    /** @var 'asc'|'desc' */
    #[Url(as: 'sdir')]
    public string $sortDirection = 'asc';

    /** @var list<int> */
    public array $selectedAssetIds = [];

    public bool $selectPage = false;

    public string $bulkReason = '';

    private const SELECTION_SESSION_KEY = 'intranet_app_assets.bulk.assets_list.selection';

    /** @var list<string> */
    private const SORT_COLUMNS = ['model', 'created_at'];

    public function mount(): void
    {
        $this->sanitizeSortFromUrl();
        $stored = Session::get(self::SELECTION_SESSION_KEY, []);
        $this->selectedAssetIds = $this->sanitizeIds(is_array($stored) ? $stored : []);
        $this->pruneStaleSelection();
    }

    public function updatedSortColumn(): void
    {
        $this->sanitizeSortFromUrl();
        $this->resetPage();
    }

    public function updatedSortDirection(): void
    {
        if (! in_array($this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORT_COLUMNS, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeIds(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
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

    public function submitBulkMarkReturn(): void
    {
        $this->authorize('manage-app-assets');

        $this->validate([
            'selectedAssetIds' => ['required', 'array', 'min:1'],
            'selectedAssetIds.*' => ['integer', 'min:1'],
            'bulkReason' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        BulkAdminWorkflowSession::put(
            BulkAdminWorkflowSession::FLOW_RETURN_INITIATE,
            $this->selectedAssetIds,
            [
                'bulk_reason' => trim($this->bulkReason),
            ],
        );

        $this->redirect(route('apps.assets.admin.bulk.review'), navigate: true);
    }

    public function submitBulkMarkClarification(): void
    {
        $this->authorize('manage-app-assets');

        $this->validate([
            'selectedAssetIds' => ['required', 'array', 'min:1'],
            'selectedAssetIds.*' => ['integer', 'min:1'],
            'bulkReason' => ['nullable', 'string', 'max:5000'],
        ]);

        $service = app(AssetAdminMarkClarificationService::class);
        $adminId = (int) auth()->id();
        $note = trim($this->bulkReason);
        $note = $note !== '' ? $note : null;

        $assets = Asset::query()
            ->whereIn('id', $this->selectedAssetIds)
            ->where('is_clarification', false)
            ->get()
            ->keyBy('id');

        $processed = 0;
        $failed = 0;

        foreach ($this->selectedAssetIds as $assetId) {
            $asset = $assets->get($assetId);
            if ($asset === null) {
                $failed++;

                continue;
            }

            try {
                $service->mark($asset, $adminId, $note);
                $processed++;
            } catch (\InvalidArgumentException) {
                $failed++;
            }
        }

        $this->selectedAssetIds = [];
        $this->selectPage = false;
        Session::forget(self::SELECTION_SESSION_KEY);
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
        unset($this->assets);

        if ($processed > 0 && $failed === 0) {
            Flux::toast($processed === 1
                ? '1 Asset wurde als „In Klärung“ markiert.'
                : $processed.' Assets wurden als „In Klärung“ markiert.', variant: 'success');

            return;
        }

        if ($processed > 0) {
            Flux::toast($processed.' markiert, '.$failed.' übersprungen.', variant: 'warning');

            return;
        }

        Flux::toast('Keine der ausgewählten Assets konnte in Klärung gesetzt werden.', variant: 'danger');
    }

    public function markAsClarification(int $assetId): void
    {
        $this->authorize('manage-app-assets');

        $asset = Asset::query()->findOrFail($assetId);

        try {
            app(AssetAdminMarkClarificationService::class)->mark($asset, (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        unset($this->assets, $this->pendingReturnsByAssetId);

        Flux::toast('Asset wurde als „In Klärung“ markiert.', variant: 'success');
    }

    public function initiateReturn(int $assetId): void
    {
        $this->authorize('manage-app-assets');

        $asset = Asset::query()->findOrFail($assetId);
        $adminId = (int) auth()->id();

        $handover = app(\Hwkdo\IntranetAppAssets\Support\ReturnInitiatableHandoverResolver::class)
            ->forAsset($asset);

        if ($handover === null) {
            Flux::toast('Für dieses Asset ist keine rückgabefähige Übergabe im aktuellen Lifecycle vorhanden.', variant: 'danger');

            return;
        }

        DB::transaction(function () use ($handover, $asset, $adminId): void {
            $return = AssetReturn::query()->create([
                'handover_id' => $handover->id,
                'initiated_by_user_id' => $adminId,
                'schedule_type' => ReturnScheduleType::Immediate,
            ]);

            $asset->historyEntries()->create([
                'event' => AssetHistory::EventReturnInitiatedByHolder,
                'user_id' => $adminId,
                'reason' => 'Rückgabe eingeleitet (Alle Assets).',
                'meta' => [
                    'asset_return_id' => $return->id,
                    'handover_id' => $handover->id,
                    'initiated_by_admin' => true,
                    'schedule_type' => ReturnScheduleType::Immediate->value,
                    'from_liste' => true,
                ],
            ]);
        });

        unset(
            $this->assets,
            $this->returnInitiatableHandoversByAssetId,
            $this->pendingReturnsByAssetId,
            $this->openHandoversByAssetId,
            $this->rejectedHandoversByAssetId,
        );

        Flux::toast('Rückgabe wurde eingeleitet.', variant: 'success');
    }

    /**
     * @return list<int>
     */
    private function validatedTypeIdsForQuery(): array
    {
        $allowed = AssetType::query()->pluck('id')->map(fn (int|string $id): int => (int) $id)->all();
        $selected = array_values(array_unique(array_filter(array_map('intval', $this->typeIds))));

        return array_values(array_intersect($selected, $allowed));
    }

    #[Computed]
    public function listingSortColumn(): string
    {
        return in_array($this->sortColumn, self::SORT_COLUMNS, true) ? $this->sortColumn : 'model';
    }

    #[Computed]
    public function listingSortDirection(): string
    {
        return $this->sortDirection === 'desc' ? 'desc' : 'asc';
    }

    protected function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Asset::query()
            ->with(['type', 'vendor', 'owner.standort']);

        return $this->applyListingSort($query);
    }

    /**
     * Exporte bleiben stabil nach Modell sortiert, unabhängig von der Tabellensortierung.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Asset>
     */
    protected function exportBaseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Asset::query()
            ->with(['type', 'vendor', 'owner.standort'])
            ->orderBy('model')
            ->orderBy('id');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Asset>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Asset>
     */
    protected function applyListingSort(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $column = $this->listingSortColumn;
        $direction = $this->listingSortDirection;

        return $query
            ->orderBy($column, $direction)
            ->orderBy('id', $direction);
    }

    #[Computed]
    public function allowAssetDirectCreate(): bool
    {
        return IntranetAppAssetsSettings::resolvedAppSettings()->allowAssetDirectCreate;
    }

    #[Computed]
    public function assets(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $typeIds = $this->validatedTypeIdsForQuery();

        return $this->baseQuery()
            ->when(filled($this->search), fn ($query) => $query->matchingListeSearch($this->search))
            ->when($typeIds !== [], fn ($q) => $q->whereIn('asset_type_id', $typeIds))
            ->when($this->statusFilter === 'missing', fn ($q) => $q->where('is_missing', true))
            ->when($this->statusFilter === 'clarification', fn ($q) => $q->where('is_clarification', true))
            ->when($this->statusFilter === 'in_stock', fn ($q) => $q->where('is_in_stock', true))
            ->when($this->statusFilter === 'shared', fn ($q) => $q->whereNull('user_id')->where('is_in_stock', false))
            ->paginate(25);
    }

    #[Computed]
    public function assetTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return AssetType::allOrdered();
    }

    /** @return \Illuminate\Support\Collection<int, Handover> keyed by asset_id */
    #[Computed]
    public function returnInitiatableHandoversByAssetId(): \Illuminate\Support\Collection
    {
        $assetIds = $this->assets->getCollection()->pluck('id');
        if ($assetIds->isEmpty()) {
            return collect();
        }

        return app(\Hwkdo\IntranetAppAssets\Support\ReturnInitiatableHandoverResolver::class)
            ->forAssetIds($assetIds);
    }

    /** @return \Illuminate\Support\Collection<int, AssetReturn> keyed by asset_id */
    #[Computed]
    public function pendingReturnsByAssetId(): \Illuminate\Support\Collection
    {
        $assetIds = $this->assets->getCollection()->pluck('id');
        if ($assetIds->isEmpty()) {
            return collect();
        }

        return AssetReturn::query()
            ->open()
            ->whereHas('handover', fn ($q) => $q->whereIn('asset_id', $assetIds)->whereNull('superseded_at'))
            ->with('handover')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (AssetReturn $assetReturn): bool => $assetReturn->handover !== null)
            ->unique(fn (AssetReturn $assetReturn): int => (int) $assetReturn->handover->asset_id)
            ->keyBy(fn (AssetReturn $assetReturn): int => (int) $assetReturn->handover->asset_id);
    }

    /** @return \Illuminate\Support\Collection<int, Handover> keyed by asset_id */
    #[Computed]
    public function openHandoversByAssetId(): \Illuminate\Support\Collection
    {
        $assetIds = $this->assets->getCollection()->pluck('id');
        if ($assetIds->isEmpty()) {
            return collect();
        }

        return Handover::query()
            ->open()
            ->whereIn('asset_id', $assetIds)
            ->orderByDesc('id')
            ->get()
            ->unique('asset_id')
            ->keyBy('asset_id');
    }

    /** @return \Illuminate\Support\Collection<int, Handover> keyed by asset_id */
    #[Computed]
    public function rejectedHandoversByAssetId(): \Illuminate\Support\Collection
    {
        $assetIds = $this->assets->getCollection()->pluck('id');
        if ($assetIds->isEmpty()) {
            return collect();
        }

        return Handover::query()
            ->rejectedPendingAdmin()
            ->whereIn('asset_id', $assetIds)
            ->orderByDesc('rejected_at')
            ->orderByDesc('id')
            ->get()
            ->unique('asset_id')
            ->keyBy('asset_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Asset>
     */
    public function getExportQueryAll(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->exportBaseQuery();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Asset>
     */
    public function getExportQueryFiltered(): \Illuminate\Database\Eloquent\Builder
    {
        $typeIds = $this->validatedTypeIdsForQuery();

        return $this->exportBaseQuery()
            ->when(filled($this->search), fn ($query) => $query->matchingListeSearch($this->search))
            ->when($typeIds !== [], fn ($q) => $q->whereIn('asset_type_id', $typeIds))
            ->when($this->statusFilter === 'missing', fn ($q) => $q->where('is_missing', true))
            ->when($this->statusFilter === 'clarification', fn ($q) => $q->where('is_clarification', true))
            ->when($this->statusFilter === 'in_stock', fn ($q) => $q->where('is_in_stock', true))
            ->when($this->statusFilter === 'shared', fn ($q) => $q->whereNull('user_id')->where('is_in_stock', false));
    }

    /**
     * @return array<int, array{heading: string, value: callable(Asset): string|int|null}>
     */
    public function getExportColumns(): array
    {
        return [
            ['heading' => 'Modell / Name', 'value' => fn (Asset $a) => $a->display_name.($a->itexia_id ? ' ('.$a->itexia_id.')' : '')],
            ['heading' => 'Seriennummer', 'value' => fn (Asset $a) => $a->serial_number],
            ['heading' => 'Typ', 'value' => fn (Asset $a) => $a->type?->name ?? '—'],
            ['heading' => 'Hersteller', 'value' => fn (Asset $a) => $a->vendor?->name ?? '—'],
            ['heading' => 'Besitzer', 'value' => fn (Asset $a) => $a->owner?->name ?? '—'],
            ['heading' => 'Standort', 'value' => fn (Asset $a) => AssetLocationDisplayResolver::resolve($a)['value'] ?? '—'],
            ['heading' => 'Status', 'value' => function (Asset $a) {
                $status = [];
                if ($a->is_missing) {
                    $status[] = 'Vermisst';
                }
                if ($a->is_clarification) {
                    $status[] = 'Klärung';
                }
                if ($a->is_in_stock) {
                    $status[] = 'Auf Lager';
                } elseif ($a->user_id === null) {
                    $status[] = 'Gemeinschaftsgerät';
                }
                if ($status === []) {
                    $status[] = 'OK';
                }

                return implode(', ', $status);
            }],
            ['heading' => 'Angelegt', 'value' => fn (Asset $a) => $a->created_at?->timezone((string) config('app.timezone'))->format('d.m.Y H:i') ?? '—'],
        ];
    }

    public function getExportFilename(string $mode): string
    {
        return $mode === 'all' ? 'assets-alle.xlsx' : 'assets-gefiltert.xlsx';
    }

    public function exportExcelAll(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(
            new AssetsTableExport($this->getExportQueryAll(), $this->getExportColumns()),
            $this->getExportFilename('all')
        );
    }

    public function exportExcelFiltered(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(
            new AssetsTableExport($this->getExportQueryFiltered(), $this->getExportColumns()),
            $this->getExportFilename('filtered')
        );
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
     * Asset-IDs der aktuellen Seite, für die die Bulk-Checkbox angezeigt wird
     * (Rückgabe einleitbar und/oder noch nicht in Klärung).
     *
     * @return list<int>
     */
    public function currentPageBulkSelectableAssetIds(): array
    {
        $handovers = $this->returnInitiatableHandoversByAssetId;
        $ids = [];
        foreach ($this->assets->getCollection() as $asset) {
            $isAdmin = auth()->user()?->can('manage-app-assets') ?? false;
            $canInitiate = (($asset->user_id !== null && (int) $asset->user_id === (int) auth()->id()) || $isAdmin);
            $returnHandover = $canInitiate ? $handovers->get($asset->id) : null;
            $clarificationEligible = $isAdmin && ! $asset->is_clarification;

            if ($returnHandover !== null || $clarificationEligible) {
                $ids[] = (int) $asset->id;
            }
        }

        return $ids;
    }

    private function areAllCurrentPageSelected(): bool
    {
        $currentIds = $this->currentPageBulkSelectableAssetIds();
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

        $returnEligible = app(\Hwkdo\IntranetAppAssets\Support\ReturnInitiatableHandoverResolver::class)
            ->forAssetIds($this->selectedAssetIds)
            ->keys()
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $clarificationEligible = Asset::query()
            ->whereIn('id', $this->selectedAssetIds)
            ->where('is_clarification', false)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $eligible = array_values(array_unique([...$returnEligible, ...$clarificationEligible]));
        $pruned = array_values(array_intersect($this->selectedAssetIds, $eligible));
        if ($pruned !== $this->selectedAssetIds) {
            $this->selectedAssetIds = $pruned;
            Session::put(self::SELECTION_SESSION_KEY, $this->selectedAssetIds);
            $this->selectPage = $this->areAllCurrentPageSelected();
        }
    }

    private function sanitizeSortFromUrl(): void
    {
        if (! in_array($this->sortColumn, self::SORT_COLUMNS, true)) {
            $this->sortColumn = 'model';
        }

        if (! in_array($this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = 'asc';
        }
    }
}; ?>
<div>
<x-intranet-app-assets::assets-layout heading="Alle Assets" subheading="Übersicht aller verwalteten Assets">
    @php
        $pageBulkAssetIds = $this->currentPageBulkSelectableAssetIds();
    @endphp
    <div
        class="space-y-4"
        x-data="{
            selectedIds: @json($selectedAssetIds),
            pageIds: @json($pageBulkAssetIds),
            syncWire() {
                $wire.set('selectedAssetIds', this.selectedIds.slice().sort((a, b) => a - b));
            },
            toggleRow(id, checked) {
                id = Number(id);
                if (checked) {
                    if (! this.selectedIds.includes(id)) {
                        this.selectedIds.push(id);
                    }
                } else {
                    this.selectedIds = this.selectedIds.filter((i) => i !== id);
                }
                this.syncWire();
            },
            togglePage(checked) {
                if (checked) {
                    this.pageIds.forEach((id) => {
                        id = Number(id);
                        if (! this.selectedIds.includes(id)) {
                            this.selectedIds.push(id);
                        }
                    });
                } else {
                    const onPage = new Set(this.pageIds.map(Number));
                    this.selectedIds = this.selectedIds.filter((i) => ! onPage.has(Number(i)));
                }
                this.syncWire();
            },
            get pageFullySelected() {
                if (this.pageIds.length === 0) {
                    return false;
                }
                return this.pageIds.every((id) => this.selectedIds.includes(Number(id)));
            },
        }"
        x-on:{{ \Hwkdo\IntranetAppAssets\Support\BulkSelectionUi::CLEAR_SELECTED_IDS_EVENT }}.window="selectedIds = []"
    >
        <flux:card
            class="overflow-hidden transition-all duration-200"
            x-bind:class="selectedIds.length > 0 ? 'ring-2 ring-amber-500/90 ring-offset-2 ring-offset-white border-amber-400/90 bg-amber-50/90 shadow-sm dark:ring-amber-400/80 dark:ring-offset-zinc-950 dark:border-amber-500/60 dark:bg-amber-950/35' : ''"
        >
            <flux:accordion exclusive transition>
                <flux:accordion.item>
                    <flux:accordion.heading
                        class="cursor-pointer select-none font-medium rounded-md px-1 py-0.5 -mx-1 transition-colors"
                        x-bind:class="selectedIds.length > 0 ? 'bg-amber-100/90 text-amber-950 dark:bg-amber-900/45 dark:text-amber-50' : ''"
                    >
                        <span x-text="selectedIds.length"></span> ausgewählt
                    </flux:accordion.heading>
                    <flux:accordion.content>
                        <div class="pt-2">
                            <div x-show="selectedIds.length === 0" x-cloak class="mb-0">
                                <flux:callout variant="subtle" icon="information-circle" class="mb-0">
                                    <flux:callout.text>
                                        Wählen Sie einen oder mehrere Datensätze über die Checkbox in der ersten Tabellenspalte aus, um anschließend eine Mehrfachaktion anzuwenden (z. B. In Klärung setzen oder Rückgabe einleiten).
                                    </flux:callout.text>
                                </flux:callout>
                            </div>
                            <div x-show="selectedIds.length > 0" x-cloak class="space-y-4">
                                <flux:textarea wire:model="bulkReason" label="Grund / Notiz (optional für Klärung, Pflicht für Rückgabe)" rows="3" />
                                <div class="flex flex-wrap gap-2">
                                    <flux:button
                                        wire:click="submitBulkMarkClarification"
                                        wire:confirm="Ausgewählte Assets wirklich als In Klärung markieren?"
                                        variant="outline"
                                        icon="question-mark-circle"
                                    >
                                        In Klärung setzen
                                    </flux:button>
                                    <flux:button wire:click="submitBulkMarkReturn" variant="primary" icon="arrow-uturn-left">Für Rückgabe markieren</flux:button>
                                    <flux:button wire:click="clearSelection" variant="ghost">Auswahl leeren</flux:button>
                                </div>
                            </div>
                        </div>
                    </flux:accordion.content>
                </flux:accordion.item>
            </flux:accordion>
        </flux:card>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 flex-wrap items-center gap-3">
                <div class="min-w-64 max-w-sm flex-1">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suchen nach SN, Modell, Name, Typ, Hersteller, Besitzer, Standort…"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>
                <flux:pillbox
                    wire:model.live="typeIds"
                    multiple
                    searchable
                    search:placeholder="Typ suchen…"
                    placeholder="Typen filtern…"
                    size="sm"
                    class="min-w-52 max-w-md shrink-0 [&_[data-flux-pillbox-placeholder]]:text-zinc-700 dark:[&_[data-flux-pillbox-placeholder]]:text-zinc-300"
                >
                    @foreach($this->assetTypes as $type)
                        <flux:pillbox.option value="{{ $type->id }}">{{ $type->name }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
                <flux:select wire:model.live="statusFilter" placeholder="Alle Status" class="w-52 shrink-0">
                    <flux:select.option value="">Alle Status</flux:select.option>
                    <flux:select.option value="missing">Vermisst</flux:select.option>
                    <flux:select.option value="clarification">In Klärung</flux:select.option>
                    <flux:select.option value="in_stock">Auf Lager</flux:select.option>
                    <flux:select.option value="shared">Gemeinschaftsgerät</flux:select.option>
                </flux:select>
            </div>
            <div class="flex items-center gap-2">
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" icon="arrow-down-tray" icon-trailing="chevron-down" wire:loading.attr="disabled">
                        Excel-Export
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item wire:click="exportExcelAll" icon="document-duplicate">Alle Daten exportieren</flux:menu.item>
                        <flux:menu.item wire:click="exportExcelFiltered" icon="funnel">Gefilterte Daten exportieren</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                @can('manage-app-assets')
                    @if($this->allowAssetDirectCreate)
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="primary" icon="plus" icon-trailing="chevron-down">
                                Neues Asset
                            </flux:button>
                            <flux:menu>
                                <flux:menu.item href="{{ route('apps.assets.create') }}" icon="pencil-square">Direkteingabe</flux:menu.item>
                                <flux:menu.item href="{{ route('apps.assets.create.wizard') }}" icon="cursor-arrow-rays">Assistent</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    @else
                        <flux:button href="{{ route('apps.assets.create.wizard') }}" variant="primary" icon="plus">
                            Neues Asset
                        </flux:button>
                    @endif
                @endcan
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>
                    <label class="inline-flex cursor-pointer items-center">
                        <input
                            type="checkbox"
                            class="size-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500 dark:border-zinc-600 dark:bg-zinc-800 dark:ring-offset-zinc-900"
                            :checked="pageFullySelected"
                            x-on:change="togglePage($event.target.checked)"
                        />
                    </label>
                </flux:table.column>
                <flux:table.column>Modell / Name</flux:table.column>
                <flux:table.column>Seriennummer</flux:table.column>
                <flux:table.column>Typ</flux:table.column>
                <flux:table.column class="w-36">Hersteller</flux:table.column>
                <flux:table.column class="w-36">Besitzer</flux:table.column>
                <flux:table.column class="w-36">Standort</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column
                    sortable
                    :sorted="$this->listingSortColumn === 'created_at'"
                    :direction="$this->listingSortDirection"
                    wire:click="sortBy('created_at')"
                    align="end"
                >Angelegt</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->assets as $asset)
                    <flux:table.row wire:key="{{ $asset->id }}">
                        <flux:table.cell>
                            @php
                                $isAdmin = auth()->user()?->can('manage-app-assets') ?? false;
                                $canInitiateReturn = (($asset->user_id !== null && (int) $asset->user_id === (int) auth()->id()) || $isAdmin);
                                $returnHandover = $canInitiateReturn ? $this->returnInitiatableHandoversByAssetId->get($asset->id) : null;
                                $clarificationEligible = $isAdmin && ! $asset->is_clarification;
                                $isBulkSelectable = $returnHandover !== null || $clarificationEligible;
                            @endphp
                            @if($isBulkSelectable)
                                <label class="inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500 dark:border-zinc-600 dark:bg-zinc-800 dark:ring-offset-zinc-900"
                                        :checked="selectedIds.includes({{ (int) $asset->id }})"
                                        x-on:change="toggleRow({{ (int) $asset->id }}, $event.target.checked)"
                                    />
                                </label>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $asset->display_name }}</div>
                            @if($asset->itexia_id)
                                <div class="text-xs text-zinc-500">{{ $asset->itexia_id }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $asset->serial_number }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->type?->name }}</flux:table.cell>
                        <flux:table.cell class="overflow-hidden">
                            @php $vendorName = $asset->vendor?->name ?? '—'; @endphp
                            @if($vendorName !== '—')
                                <flux:tooltip :content="$vendorName" position="top">
                                    <span class="block min-w-0 truncate">{{ $vendorName }}</span>
                                </flux:tooltip>
                            @else
                                <span>—</span>
                            @endif
                        </flux:table.cell>
                        @php
                            $ownerName = $asset->owner?->name;
                            $locationValue = AssetLocationDisplayResolver::resolve($asset)['value'];
                        @endphp
                        <flux:table.cell class="overflow-hidden">
                            @if(filled($ownerName))
                                <flux:tooltip :content="$ownerName" position="top">
                                    <span class="block min-w-0 truncate">{{ $ownerName }}</span>
                                </flux:tooltip>
                            @else
                                <span>—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="overflow-hidden">
                            @if(filled($locationValue))
                                <flux:tooltip :content="$locationValue" position="top">
                                    <span class="block min-w-0 truncate">{{ $locationValue }}</span>
                                </flux:tooltip>
                            @else
                                <span>—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @if($asset->is_missing)
                                    <flux:badge color="red" size="sm">Vermisst</flux:badge>
                                @endif
                                @if($asset->is_clarification)
                                    <flux:badge color="amber" size="sm">Klärung</flux:badge>
                                @endif
                                @if($asset->is_in_stock)
                                    <flux:badge color="blue" size="sm">Auf Lager</flux:badge>
                                @elseif($asset->user_id === null)
                                    <flux:badge color="zinc" size="sm">Gemeinschaftsgerät</flux:badge>
                                @elseif(! $asset->is_missing && ! $asset->is_clarification)
                                    <flux:badge color="green" size="sm">OK</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        @php
                            $createdAt = $asset->created_at;
                            $createdRelative = $createdAt ? $createdAt->diffForHumans(['parts' => 1]) : '—';
                            $createdAbsolute = $createdAt ? $createdAt->timezone((string) config('app.timezone'))->format('d.m.Y H:i') : '';
                        @endphp
                        <flux:table.cell class="w-24 max-w-[6.5rem] whitespace-nowrap text-end text-sm text-zinc-600 dark:text-zinc-200">
                            @if($createdAt)
                                <flux:tooltip :content="$createdAbsolute" position="top">
                                    <span class="block truncate">{{ $createdRelative }}</span>
                                </flux:tooltip>
                            @else
                                <span>—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-1">
                                @php
                                    $adminResolveActions = AssetListeAdminActions::resolveLinks($asset, [
                                        'pending_return' => $this->pendingReturnsByAssetId->get($asset->id),
                                        'open_handover' => $this->openHandoversByAssetId->get($asset->id),
                                        'rejected_handover' => $this->rejectedHandoversByAssetId->get($asset->id),
                                    ]);
                                    $returnHandover = $this->returnInitiatableHandoversByAssetId->get($asset->id);
                                    $canAdminHandover = \Hwkdo\IntranetAppAssets\Support\AdminHandoverEligibility::isEligibleForListeRow(
                                        $asset,
                                        $this->pendingReturnsByAssetId,
                                        $this->openHandoversByAssetId,
                                    );
                                @endphp
                                @if($canAdminHandover)
                                    <flux:tooltip content="Übergeben" position="top">
                                        <flux:button
                                            :href="route('apps.assets.admin.handover.start', $asset)"
                                            variant="ghost"
                                            size="sm"
                                            icon="hand-raised"
                                            class="!px-2"
                                            aria-label="Übergeben"
                                        ></flux:button>
                                    </flux:tooltip>
                                @endif
                                @foreach($adminResolveActions as $action)
                                    <flux:tooltip :content="$action['label']" position="top">
                                        <flux:button
                                            :href="$action['href']"
                                            variant="ghost"
                                            size="sm"
                                            :icon="$action['icon']"
                                            class="!px-2"
                                            :aria-label="$action['label']"
                                        ></flux:button>
                                    </flux:tooltip>
                                @endforeach
                                @if($returnHandover)
                                    <flux:tooltip content="Rückgabe einleiten" position="top">
                                        <flux:button
                                            type="button"
                                            wire:click="initiateReturn({{ (int) $asset->id }})"
                                            wire:confirm="Rückgabe für „{{ $asset->display_name }}“ wirklich einleiten?"
                                            variant="ghost"
                                            size="sm"
                                            icon="arrow-uturn-left"
                                            class="!px-2"
                                            aria-label="Rückgabe einleiten"
                                        ></flux:button>
                                    </flux:tooltip>
                                @endif
                                @can('manage-app-assets')
                                    @if(! $asset->is_clarification)
                                        <flux:tooltip content="Als In Klärung markieren" position="top">
                                            <flux:button
                                                type="button"
                                                wire:click="markAsClarification({{ (int) $asset->id }})"
                                                wire:confirm="Asset „{{ $asset->display_name }}“ wirklich als In Klärung markieren?"
                                                variant="ghost"
                                                size="sm"
                                                icon="question-mark-circle"
                                                class="!px-2"
                                                aria-label="Als In Klärung markieren"
                                            ></flux:button>
                                        </flux:tooltip>
                                    @endif
                                @endcan
                                <flux:tooltip content="Detail" position="top">
                                    <flux:button
                                        href="{{ route('apps.assets.show', [$asset, 'from' => 'liste']) }}"
                                        variant="ghost"
                                        size="sm"
                                        icon="eye"
                                        class="!px-2"
                                        aria-label="Detail"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="10" class="text-center text-zinc-500 py-8">
                            Keine Assets gefunden.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div>
            {{ $this->assets->links() }}
        </div>
    </div>
</x-intranet-app-assets::assets-layout>
</div>