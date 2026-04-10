<?php

use Hwkdo\IntranetAppAssets\Exports\AssetsTableExport;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')] #[Title('Domänengeräte')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var 'domain_last_seen'|'last_logon_timestamp'|'' */
    #[Url]
    public string $sortBy = '';

    /** @var 'asc'|'desc' */
    #[Url]
    public string $sortDir = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortByColumn(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }
        $this->resetPage();
    }

    protected function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Asset::query()
            ->with(['type', 'vendor', 'owner'])
            ->whereHas('type', fn ($q) => $q->where('is_domain_object', true));
    }

    protected function orderQuery(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $orderColumn = in_array($this->sortBy, ['domain_last_seen', 'last_logon_timestamp'], true)
            ? $this->sortBy
            : 'model';
        $orderDir = $this->sortDir === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderColumn, $orderDir);
    }

    #[Computed]
    public function assets(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->orderQuery(
            $this->baseQuery()
                ->when($this->search, function ($query) {
                    $search = $this->search;
                    $term = '%'.$search.'%';
                    $query->where(function ($q) use ($term) {
                        $q->where('serial_number', 'like', $term)
                            ->orWhere('model', 'like', $term)
                            ->orWhere('name', 'like', $term)
                            ->orWhere('itexia_id', 'like', $term)
                            ->orWhere('domain_connection', 'like', $term)
                            ->orWhere('smbios_guid', 'like', $term)
                            ->orWhere('configmgr_last_logon_user', 'like', $term)
                            ->orWhere('configmgr_mac_addresses', 'like', $term)
                            ->orWhereHas('owner', fn ($o) => $o->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term));
                    });
                })
        )->paginate(25);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Asset>
     */
    public function getExportQueryAll(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->orderQuery($this->baseQuery());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Asset>
     */
    public function getExportQueryFiltered(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->orderQuery(
            $this->baseQuery()
                ->when($this->search, function ($query) {
                    $search = $this->search;
                    $term = '%'.$search.'%';
                    $query->where(function ($q) use ($term) {
                        $q->where('serial_number', 'like', $term)
                            ->orWhere('model', 'like', $term)
                            ->orWhere('name', 'like', $term)
                            ->orWhere('itexia_id', 'like', $term)
                            ->orWhere('domain_connection', 'like', $term)
                            ->orWhere('smbios_guid', 'like', $term)
                            ->orWhere('configmgr_last_logon_user', 'like', $term)
                            ->orWhere('configmgr_mac_addresses', 'like', $term)
                            ->orWhereHas('owner', fn ($o) => $o->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term));
                    });
                })
        );
    }

    /**
     * @return array<int, array{heading: string, value: callable(Asset): string|int|null}>
     */
    public function getExportColumns(): array
    {
        return [
            ['heading' => 'Modell / Name', 'value' => fn (Asset $a) => $a->display_name.($a->itexia_id ? ' ('.$a->itexia_id.')' : '')],
            ['heading' => 'Seriennummer', 'value' => fn (Asset $a) => $a->serial_number],
            ['heading' => 'Domäne', 'value' => fn (Asset $a) => $a->domain_connection ?? '—'],
            ['heading' => 'Last seen', 'value' => fn (Asset $a) => $a->domain_last_seen?->format('d.m.Y H:i') ?? '—'],
            ['heading' => 'Last Logon', 'value' => fn (Asset $a) => $a->last_logon_timestamp?->format('d.m.Y H:i') ?? '—'],
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
        return $mode === 'all' ? 'domaenengeraete-alle.xlsx' : 'domaenengeraete-gefiltert.xlsx';
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
}; ?>
<div>
<x-intranet-app-assets::assets-layout heading="Domänengeräte" subheading="Assets vom Typ Domänengerät (is_domain_object)">
    <div class="space-y-4">
        @can('manage-app-assets')
            <div class="grid gap-4 sm:grid-cols-2">
                {{-- Helles Infopanel bewusst auch im Dark Mode: immer dunkle Schrift (Flux würde sonst helle Dark-Textfarbe auf hellem Grund erzwingen). --}}
                <div class="rounded-xl border-2 border-amber-400/90 bg-amber-50 p-4 text-zinc-900 shadow-sm dark:border-amber-500/70 dark:bg-amber-50 dark:text-zinc-900">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex gap-3">
                            <flux:icon icon="arrows-right-left" class="size-10 shrink-0 text-amber-800 dark:text-amber-800" />
                            <div class="min-w-0">
                                {{-- Kein flux:heading/flux:text: Flux erzwingt Dark-Mode-Textfarben, die auf dem hellen amber-Panel unsichtbar werden. --}}
                                <h3 class="text-sm font-semibold leading-snug text-zinc-900">Domain-Abgleich (AD vs. Assets)</h3>
                                <p class="mt-1.5 text-sm leading-relaxed text-zinc-800">
                                    Vergleichen Sie diese Liste mit den Computerkonten in Active Directory (Verwaltung/Schulung) und finden Sie fehlende oder zusätzliche Einträge.
                                </p>
                            </div>
                        </div>
                        <flux:button :href="route('apps.assets.domain-compare')" variant="primary" icon="arrows-right-left" wire:navigate class="w-full shrink-0 sm:w-auto">
                            Domain-Abgleich öffnen
                        </flux:button>
                    </div>
                </div>
                <div class="rounded-xl border-2 border-amber-400/90 bg-amber-50 p-4 text-zinc-900 shadow-sm dark:border-amber-500/70 dark:bg-amber-50 dark:text-zinc-900">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex gap-3">
                            <flux:icon icon="server" class="size-10 shrink-0 text-amber-800 dark:text-amber-800" />
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold leading-snug text-zinc-900">SCCM-Abgleich (AD vs. SCCM)</h3>
                                <p class="mt-1.5 text-sm leading-relaxed text-zinc-800">
                                    Ermitteln Sie pro Domäne, welche Computer in den AD-OUs vorkommen, aber nicht in ConfigMgr/SCCM registriert sind.
                                </p>
                            </div>
                        </div>
                        <flux:button :href="route('apps.assets.sccm-compare')" variant="primary" icon="server" wire:navigate class="w-full shrink-0 sm:w-auto">
                            SCCM-Abgleich öffnen
                        </flux:button>
                    </div>
                </div>
            </div>
        @endcan
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 gap-3">
                <div class="flex-1 max-w-sm">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suchen nach SN, Modell, Name, Domäne, SMBIOS-GUID, MAC, Last-Logon-User…"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>
            </div>
            <flux:dropdown position="bottom" align="end">
                <flux:button variant="ghost" icon="arrow-down-tray" icon-trailing="chevron-down" wire:loading.attr="disabled">
                    Excel-Export
                </flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="exportExcelAll" icon="document-duplicate">Alle Daten exportieren</flux:menu.item>
                    <flux:menu.item wire:click="exportExcelFiltered" icon="funnel">Gefilterte Daten exportieren</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Modell / Name</flux:table.column>
                <flux:table.column>Seriennummer</flux:table.column>
                <flux:table.column>Domäne</flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortByColumn('domain_last_seen')" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-zinc-100">
                        Last seen
                        @if($this->sortBy === 'domain_last_seen')
                            <flux:icon icon="{{ $this->sortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-4" />
                        @else
                            <flux:icon icon="chevron-up-down" class="size-4 opacity-50" />
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortByColumn('last_logon_timestamp')" class="inline-flex items-center gap-1 font-medium hover:text-zinc-900 dark:hover:text-zinc-100">
                        Last Logon
                        @if($this->sortBy === 'last_logon_timestamp')
                            <flux:icon icon="{{ $this->sortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-4" />
                        @else
                            <flux:icon icon="chevron-up-down" class="size-4 opacity-50" />
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>Typ</flux:table.column>
                <flux:table.column>Hersteller</flux:table.column>
                <flux:table.column>Besitzer</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->assets as $asset)
                    <flux:table.row wire:key="domain-{{ $asset->id }}">
                        <flux:table.cell>
                            <div class="font-medium">{{ $asset->display_name }}</div>
                            @if($asset->itexia_id)
                                <div class="text-xs text-zinc-500">{{ $asset->itexia_id }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $asset->serial_number }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $asset->domain_connection ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="text-sm">
                            {{ $asset->domain_last_seen?->format('d.m.Y H:i') ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">
                            {{ $asset->last_logon_timestamp?->format('d.m.Y H:i') ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $asset->type?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->vendor?->name }}</flux:table.cell>
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
                            <flux:button href="{{ route('apps.assets.show', $asset) }}" variant="ghost" size="sm" icon="eye" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="10" class="text-center text-zinc-500 py-8">
                            Keine Domänengeräte gefunden.
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
