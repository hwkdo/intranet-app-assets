<?php

use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Hwkdo\IntranetAppAssets\Services\LegacyAssetImportService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Legacy-Assets')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'itexia')]
    public bool $onlyWithItexiaId = false;

    #[Url(as: 'missing')]
    public bool $showOnlyMissing = true;

    #[Url(as: 'sort')]
    public string $sortBy = 'updated_at';

    #[Url(as: 'dir')]
    public string $sortDir = 'desc';

    /** @var list<int> */
    public array $selectedLegacyIds = [];

    public bool $selectPage = false;

    private const SELECTION_SESSION_KEY = 'intranet_app_assets.bulk.legacy_assets.selection';

    public function mount(): void
    {
        $this->authorize('manage-app-assets');
        $stored = Session::get(self::SELECTION_SESSION_KEY, []);
        $this->selectedLegacyIds = $this->sanitizeIds(is_array($stored) ? $stored : []);
        $this->pruneStaleSelection();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyWithItexiaId(): void
    {
        $this->resetPage();
    }

    public function updatedShowOnlyMissing(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function updatedSortDir(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedLegacyIds(): void
    {
        $this->selectedLegacyIds = $this->sanitizeIds($this->selectedLegacyIds);
        Session::put(self::SELECTION_SESSION_KEY, $this->selectedLegacyIds);
        $this->selectPage = $this->areAllCurrentPageSelected();
        $this->skipRender();
    }

    public function clearSelection(): void
    {
        $this->selectedLegacyIds = [];
        $this->selectPage = false;
        Session::forget(self::SELECTION_SESSION_KEY);
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
        $this->skipRender();
    }

    public function sortByColumn(string $column): void
    {
        $allowed = ['updated_at', 'id', 'itexiaid', 'modell', 'sn'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = $column === 'updated_at' ? 'desc' : 'asc';
        }
    }

    public function importSelected(LegacyAssetImportService $importer, IntranetLegacyService $legacyService): void
    {
        $this->authorize('manage-app-assets');

        $this->validate([
            'selectedLegacyIds' => ['required', 'array', 'min:1'],
            'selectedLegacyIds.*' => ['integer', 'min:1'],
        ]);

        $result = $importer->importMissingByLegacyIds($legacyService, $this->legacyAssetsRaw, $this->selectedLegacyIds);

        $this->clearSelection();
        session()->flash(
            'success',
            'Legacy-Import abgeschlossen: '.$result['imported'].' importiert, '.$result['skipped'].' übersprungen (Auswahl: '.$result['selected'].').'
        );
    }

    public function importSingle(int $legacyId, LegacyAssetImportService $importer, IntranetLegacyService $legacyService): void
    {
        $this->authorize('manage-app-assets');

        $result = $importer->importMissingByLegacyIds($legacyService, $this->legacyAssetsRaw, [$legacyId]);
        session()->flash(
            'success',
            'Legacy-Asset importiert: '.$result['imported'].' importiert, '.$result['skipped'].' übersprungen.'
        );
    }

    #[Computed]
    public function legacyAssetsRaw(): array
    {
        /** @var LegacyAssetImportService $importer */
        $importer = app(LegacyAssetImportService::class);
        /** @var IntranetLegacyService $legacyService */
        $legacyService = app(IntranetLegacyService::class);

        return $importer->fetchLegacyAssets($legacyService, 'all');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function comparedRows(): array
    {
        /** @var LegacyAssetImportService $importer */
        $importer = app(LegacyAssetImportService::class);
        $legacyAssets = $this->legacyAssetsRaw;
        $map = $importer->buildLegacyToLocalAssetMap($legacyAssets);

        $rows = [];
        foreach ($legacyAssets as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            $itexiaId = trim((string) ($legacy['itexiaid'] ?? ''));
            if ($this->onlyWithItexiaId && $itexiaId === '') {
                continue;
            }

            $isMissing = ! isset($map[$legacyId]);
            if ($this->showOnlyMissing && ! $isMissing) {
                continue;
            }

            $searchHaystack = strtolower(trim(implode(' ', [
                (string) ($legacy['modell'] ?? ''),
                (string) ($legacy['name'] ?? ''),
                (string) ($legacy['sn'] ?? ''),
                (string) ($legacy['itexiaid'] ?? ''),
                (string) ($legacy['standort'] ?? ''),
            ])));

            if ($this->search !== '' && ! str_contains($searchHaystack, strtolower($this->search))) {
                continue;
            }

            $updatedAt = $legacy['updated_at'] ?? null;
            $sortTs = $updatedAt ? strtotime((string) $updatedAt) : false;

            $rows[] = [
                'legacy_id' => $legacyId,
                'is_missing' => $isMissing,
                'local_asset_id' => $map[$legacyId] ?? null,
                'itexiaid' => $legacy['itexiaid'] ?? null,
                'modell' => $legacy['modell'] ?? null,
                'name' => $legacy['name'] ?? null,
                'sn' => $legacy['sn'] ?? null,
                'standort' => $legacy['standort'] ?? null,
                'updated_at' => $updatedAt,
                'sort_updated_at' => $sortTs !== false ? (int) $sortTs : 0,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $col = $this->sortBy;
            $dir = $this->sortDir === 'asc' ? 1 : -1;

            $left = $col === 'updated_at' ? $a['sort_updated_at'] : ($a[$col] ?? '');
            $right = $col === 'updated_at' ? $b['sort_updated_at'] : ($b[$col] ?? '');

            if ($left === $right) {
                return 0;
            }

            return ($left <=> $right) * $dir;
        });

        return $rows;
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $all = $this->comparedRows;
        $perPage = 25;
        $page = $this->getPage();
        $items = array_slice($all, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator(
            $items,
            count($all),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );
    }

    /**
     * @return list<int>
     */
    public function currentPageSelectableLegacyIds(): array
    {
        $ids = [];
        foreach ($this->rows->items() as $row) {
            if (($row['is_missing'] ?? false) === true) {
                $ids[] = (int) $row['legacy_id'];
            }
        }

        return $ids;
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

    private function areAllCurrentPageSelected(): bool
    {
        $currentIds = $this->currentPageSelectableLegacyIds();
        if ($currentIds === []) {
            return false;
        }

        return count(array_diff($currentIds, $this->selectedLegacyIds)) === 0;
    }

    private function pruneStaleSelection(): void
    {
        if ($this->selectedLegacyIds === []) {
            return;
        }

        $valid = array_map(static fn (array $row): int => (int) $row['legacy_id'], $this->comparedRows);
        $pruned = array_values(array_intersect($this->selectedLegacyIds, $valid));
        if ($pruned !== $this->selectedLegacyIds) {
            $this->selectedLegacyIds = $pruned;
            Session::put(self::SELECTION_SESSION_KEY, $this->selectedLegacyIds);
            $this->selectPage = $this->areAllCurrentPageSelected();
        }
    }
}; ?>
<div>
<x-intranet-app-assets::assets-layout heading="Legacy-Assets" subheading="Vergleich Legacy-Bestand mit aktuellem System und Nachimport fehlender Assets">
    @php
        $pageSelectableIds = $this->currentPageSelectableLegacyIds();
    @endphp

    <div
        class="space-y-4"
        x-data="{
            selectedIds: @json($selectedLegacyIds),
            pageIds: @json($pageSelectableIds),
            syncWire() {
                $wire.set('selectedLegacyIds', this.selectedIds.slice().sort((a, b) => a - b));
            },
            toggleRow(id, checked) {
                id = Number(id);
                if (checked) {
                    if (!this.selectedIds.includes(id)) {
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
                        if (!this.selectedIds.includes(id)) {
                            this.selectedIds.push(id);
                        }
                    });
                } else {
                    const onPage = new Set(this.pageIds.map(Number));
                    this.selectedIds = this.selectedIds.filter((i) => !onPage.has(Number(i)));
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
        <flux:card>
            <flux:accordion exclusive transition>
                <flux:accordion.item>
                    <flux:accordion.heading>
                        <span x-text="selectedIds.length"></span> ausgewählt
                    </flux:accordion.heading>
                    <flux:accordion.content>
                        <div class="pt-2 space-y-3">
                            <div x-show="selectedIds.length === 0" x-cloak>
                                <flux:callout variant="subtle" icon="information-circle">
                                    <flux:callout.text>Wählen Sie fehlende Legacy-Assets per Checkbox aus, um sie gesammelt zu importieren.</flux:callout.text>
                                </flux:callout>
                            </div>
                            <div x-show="selectedIds.length > 0" x-cloak class="flex gap-2">
                                <flux:button wire:click="importSelected" variant="primary" icon="arrow-down-tray">Ausgewählte importieren</flux:button>
                                <flux:button wire:click="clearSelection" variant="ghost">Auswahl leeren</flux:button>
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
                        placeholder="Suchen nach Modell, Name, SN, Itexia-ID…"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>

                <flux:field variant="inline">
                    <flux:checkbox wire:model.live="onlyWithItexiaId" label="Nur mit Itexia-ID" />
                </flux:field>
                <flux:field variant="inline">
                    <flux:checkbox wire:model.live="showOnlyMissing" label="Nur fehlende anzeigen" />
                </flux:field>
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
                <flux:table.column>
                    <button type="button" wire:click="sortByColumn('id')" class="inline-flex items-center gap-1">
                        Legacy-ID
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortByColumn('itexiaid')" class="inline-flex items-center gap-1">
                        Itexia-ID
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortByColumn('modell')" class="inline-flex items-center gap-1">
                        Modell / Name
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortByColumn('sn')" class="inline-flex items-center gap-1">
                        Seriennummer
                    </button>
                </flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortByColumn('updated_at')" class="inline-flex items-center gap-1">
                        Legacy updated_at
                    </button>
                </flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->rows as $row)
                    <flux:table.row wire:key="legacy-asset-{{ $row['legacy_id'] }}">
                        <flux:table.cell>
                            @if($row['is_missing'])
                                <label class="inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500 dark:border-zinc-600 dark:bg-zinc-800 dark:ring-offset-zinc-900"
                                        :checked="selectedIds.includes({{ (int) $row['legacy_id'] }})"
                                        x-on:change="toggleRow({{ (int) $row['legacy_id'] }}, $event.target.checked)"
                                    />
                                </label>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $row['legacy_id'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['itexiaid'] ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $row['modell'] ?: '—' }}</div>
                            @if(filled($row['name']))
                                <div class="text-xs text-zinc-500">{{ $row['name'] }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $row['sn'] ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if($row['is_missing'])
                                <flux:badge color="amber" size="sm">Fehlt im neuen System</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">Vorhanden</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $row['updated_at'] ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if($row['is_missing'])
                                <flux:button wire:click="importSingle({{ (int) $row['legacy_id'] }})" variant="ghost" size="sm" icon="arrow-down-tray" />
                            @elseif($row['local_asset_id'])
                                <flux:button href="{{ route('apps.assets.show', $row['local_asset_id']) }}" variant="ghost" size="sm" icon="eye" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500 py-8">
                            Keine Legacy-Assets für den aktuellen Filter gefunden.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div>
            {{ $this->rows->links() }}
        </div>
    </div>
</x-intranet-app-assets::assets-layout>
</div>
