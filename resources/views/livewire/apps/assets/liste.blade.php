<?php

use Hwkdo\IntranetAppAssets\Exports\AssetsTableExport;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\BulkAdminWorkflowSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
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

    /** @var list<int> */
    public array $selectedAssetIds = [];

    public bool $selectPage = false;

    public string $bulkReason = '';

    private const SELECTION_SESSION_KEY = 'intranet_app_assets.bulk.assets_list.selection';

    public function mount(): void
    {
        $stored = Session::get(self::SELECTION_SESSION_KEY, []);
        $this->selectedAssetIds = $this->sanitizeIds(is_array($stored) ? $stored : []);
        $this->pruneStaleSelection();
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

    /**
     * @return list<int>
     */
    private function validatedTypeIdsForQuery(): array
    {
        $allowed = AssetType::query()->pluck('id')->map(fn (int|string $id): int => (int) $id)->all();
        $selected = array_values(array_unique(array_filter(array_map('intval', $this->typeIds))));

        return array_values(array_intersect($selected, $allowed));
    }

    protected function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Asset::query()
            ->with(['type', 'vendor', 'owner'])
            ->orderBy('model');
    }

    #[Computed]
    public function assets(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $typeIds = $this->validatedTypeIdsForQuery();

        return $this->baseQuery()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $term = '%'.$this->search.'%';
                    $q->where('serial_number', 'like', $term)
                        ->orWhere('model', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('itexia_id', 'like', $term)
                        ->orWhereHas('owner', fn ($o) => $o->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term));
                });
            })
            ->when($typeIds !== [], fn ($q) => $q->whereIn('asset_type_id', $typeIds))
            ->when($this->statusFilter === 'missing', fn ($q) => $q->where('is_missing', true))
            ->when($this->statusFilter === 'clarification', fn ($q) => $q->where('is_clarification', true))
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

        return Handover::query()
            ->whereIn('asset_id', $assetIds)
            ->whereNotNull('confirmed_at')
            ->whereNull('rejected_at')
            ->whereDoesntHave('assetReturns')
            ->orderByDesc('confirmed_at')
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
        return $this->baseQuery();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Asset>
     */
    public function getExportQueryFiltered(): \Illuminate\Database\Eloquent\Builder
    {
        $typeIds = $this->validatedTypeIdsForQuery();

        return $this->baseQuery()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $term = '%'.$this->search.'%';
                    $q->where('serial_number', 'like', $term)
                        ->orWhere('model', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('itexia_id', 'like', $term)
                        ->orWhereHas('owner', fn ($o) => $o->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term));
                });
            })
            ->when($typeIds !== [], fn ($q) => $q->whereIn('asset_type_id', $typeIds))
            ->when($this->statusFilter === 'missing', fn ($q) => $q->where('is_missing', true))
            ->when($this->statusFilter === 'clarification', fn ($q) => $q->where('is_clarification', true));
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
            ['heading' => 'Status', 'value' => function (Asset $a) {
                $status = [];
                if ($a->is_missing) {
                    $status[] = 'Vermisst';
                }
                if ($a->is_clarification) {
                    $status[] = 'Klärung';
                }
                if (empty($status)) {
                    $status[] = 'OK';
                }
                return implode(', ', $status);
            }],
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
     * Asset-IDs der aktuellen Seite, für die die Bulk-Checkbox „Rückgabe“ angezeigt wird.
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
            if ($canInitiate && $handovers->get($asset->id) !== null) {
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

        $eligible = Handover::query()
            ->whereIn('asset_id', $this->selectedAssetIds)
            ->whereNotNull('confirmed_at')
            ->whereNull('rejected_at')
            ->whereDoesntHave('assetReturns', fn ($q) => $q->whereNull('completed_at'))
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->get()
            ->unique('asset_id')
            ->pluck('asset_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $pruned = array_values(array_intersect($this->selectedAssetIds, $eligible));
        if ($pruned !== $this->selectedAssetIds) {
            $this->selectedAssetIds = $pruned;
            Session::put(self::SELECTION_SESSION_KEY, $this->selectedAssetIds);
            $this->selectPage = $this->areAllCurrentPageSelected();
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
                                        Wählen Sie einen oder mehrere Datensätze über die Checkbox in der ersten Tabellenspalte aus, um anschließend eine Mehrfachaktion auf mehrere Assets anwenden zu können.
                                    </flux:callout.text>
                                </flux:callout>
                            </div>
                            <div x-show="selectedIds.length > 0" x-cloak class="space-y-4">
                                <flux:textarea wire:model="bulkReason" label="Grund / Notiz (für alle ausgewählten)" rows="3" />
                                <div class="flex flex-wrap gap-2">
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
                        placeholder="Suchen nach SN, Modell, Name, Itexia-ID…"
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
                <flux:select wire:model.live="statusFilter" placeholder="Alle Status" class="w-44 shrink-0">
                    <flux:select.option value="">Alle Status</flux:select.option>
                    <flux:select.option value="missing">Vermisst</flux:select.option>
                    <flux:select.option value="clarification">In Klärung</flux:select.option>
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
                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="primary" icon="plus" icon-trailing="chevron-down">
                            Neues Asset
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item href="{{ route('apps.assets.create') }}" icon="pencil-square">Direkteingabe</flux:menu.item>
                            <flux:menu.item href="{{ route('apps.assets.create.wizard') }}" icon="cursor-arrow-rays">Assistent</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
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
                <flux:table.column>Hersteller</flux:table.column>
                <flux:table.column>Besitzer</flux:table.column>
                <flux:table.column>Status</flux:table.column>
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
                            @endphp
                            @if($returnHandover)
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
                        <flux:table.cell class="max-w-[10rem]">
                            @php $vendorName = $asset->vendor?->name ?? '—'; @endphp
                            <flux:tooltip :content="$vendorName" position="top">
                                <span class="block truncate">{{ $vendorName }}</span>
                            </flux:tooltip>
                        </flux:table.cell>
                        <flux:table.cell>{{ $asset->owner?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @if($asset->is_missing)
                                    <flux:badge color="red" size="sm">Vermisst</flux:badge>
                                @endif
                                @if($asset->is_clarification)
                                    <flux:badge color="amber" size="sm">Klärung</flux:badge>
                                @endif
                                @if(!$asset->is_missing && !$asset->is_clarification)
                                    <flux:badge color="green" size="sm">OK</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-1">
                                @php
                                    $isAdmin = auth()->user()?->can('manage-app-assets') ?? false;
                                    $canInitiateReturn = (($asset->user_id !== null && (int) $asset->user_id === (int) auth()->id()) || $isAdmin);
                                    $returnHandover = $canInitiateReturn ? $this->returnInitiatableHandoversByAssetId->get($asset->id) : null;
                                @endphp
                                @if($returnHandover)
                                    <flux:tooltip content="Rückgabe einleiten" position="top">
                                        <flux:button
                                            href="{{ route('apps.assets.handover.return.initiate', $returnHandover) }}"
                                            variant="ghost"
                                            size="sm"
                                            icon="arrow-uturn-left"
                                            class="!px-2"
                                            aria-label="Rückgabe einleiten"
                                        ></flux:button>
                                    </flux:tooltip>
                                @endif
                                <flux:button href="{{ route('apps.assets.show', $asset) }}" variant="ghost" size="sm" icon="eye" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500 py-8">
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