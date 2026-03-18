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

new #[Layout('components.layouts.app')] #[Title('Mobilgeräte')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var 'all'|'intune'|'non-intune' */
    #[Url]
    public string $intuneFilter = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedIntuneFilter(): void
    {
        $this->resetPage();
    }

    protected function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Asset::query()
            ->with(['type', 'vendor', 'owner'])
            ->whereHas('type', fn ($q) => $q->where('is_intune_object', true))
            ->orderBy('model');
    }

    #[Computed]
    public function assets(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->baseQuery()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('serial_number', 'like', "%{$this->search}%")
                        ->orWhere('model', 'like', "%{$this->search}%")
                        ->orWhere('name', 'like', "%{$this->search}%")
                        ->orWhere('itexia_id', 'like', "%{$this->search}%")
                        ->orWhere('imei', 'like', "%{$this->search}%");
                });
            })
            ->when($this->intuneFilter === 'intune', fn ($q) => $q->whereNotNull('intune_device_id'))
            ->when($this->intuneFilter === 'non-intune', fn ($q) => $q->whereNull('intune_device_id'))
            ->paginate(25);
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
        return $this->baseQuery()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('serial_number', 'like', "%{$this->search}%")
                        ->orWhere('model', 'like', "%{$this->search}%")
                        ->orWhere('name', 'like', "%{$this->search}%")
                        ->orWhere('itexia_id', 'like', "%{$this->search}%")
                        ->orWhere('imei', 'like', "%{$this->search}%");
                });
            })
            ->when($this->intuneFilter === 'intune', fn ($q) => $q->whereNotNull('intune_device_id'))
            ->when($this->intuneFilter === 'non-intune', fn ($q) => $q->whereNull('intune_device_id'));
    }

    /**
     * @return array<int, array{heading: string, value: callable(Asset): string|int|null}>
     */
    public function getExportColumns(): array
    {
        return [
            ['heading' => 'Modell / Name', 'value' => fn (Asset $a) => $a->display_name.($a->itexia_id ? ' ('.$a->itexia_id.')' : '')],
            ['heading' => 'Seriennummer', 'value' => fn (Asset $a) => $a->serial_number],
            ['heading' => 'IMEI', 'value' => fn (Asset $a) => $a->imei ?? '—'],
            ['heading' => 'Typ', 'value' => fn (Asset $a) => $a->type?->name ?? '—'],
            ['heading' => 'Hersteller', 'value' => fn (Asset $a) => $a->vendor?->name ?? '—'],
            ['heading' => 'Besitzer', 'value' => fn (Asset $a) => $a->owner?->name ?? '—'],
            ['heading' => 'Status', 'value' => function (Asset $a) {
                $status = [];
                if ($a->intune_device_id) {
                    $status[] = 'Intune';
                } else {
                    $status[] = 'Nicht-Intune';
                }
                if ($a->is_missing) {
                    $status[] = 'Vermisst';
                }
                if ($a->is_clarification) {
                    $status[] = 'Klärung';
                }
                return implode(', ', $status);
            }],
        ];
    }

    public function getExportFilename(string $mode): string
    {
        return $mode === 'all' ? 'mobilgeraete-alle.xlsx' : 'mobilgeraete-gefiltert.xlsx';
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
<x-intranet-app-assets::assets-layout heading="Mobilgeräte" subheading="Assets vom Typ Mobilgerät (is_intune_object)">
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 gap-3">
                <div class="flex-1 max-w-sm">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suchen nach SN, Modell, Name, IMEI…"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>
                <flux:select wire:model.live="intuneFilter" placeholder="Intune-Filter" class="w-52">
                    <flux:select.option value="all">Alle Mobilgeräte</flux:select.option>
                    <flux:select.option value="intune">Nur Intune-Geräte</flux:select.option>
                    <flux:select.option value="non-intune">Nur nicht-Intune-Geräte</flux:select.option>
                </flux:select>
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
                <flux:table.column>IMEI</flux:table.column>
                <flux:table.column>Typ</flux:table.column>
                <flux:table.column>Hersteller</flux:table.column>
                <flux:table.column>Besitzer</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->assets as $asset)
                    <flux:table.row wire:key="mobile-{{ $asset->id }}">
                        <flux:table.cell>
                            <div class="font-medium">{{ $asset->display_name }}</div>
                            @if($asset->itexia_id)
                                <div class="text-xs text-zinc-500">{{ $asset->itexia_id }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $asset->serial_number }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $asset->imei ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->type?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->vendor?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->owner?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @if($asset->intune_device_id)
                                    <flux:badge color="blue" size="sm">Intune</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Nicht-Intune</flux:badge>
                                @endif
                                @if($asset->is_missing)
                                    <flux:badge color="red" size="sm">Vermisst</flux:badge>
                                @endif
                                @if($asset->is_clarification)
                                    <flux:badge color="amber" size="sm">Klärung</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('apps.assets.show', $asset) }}" variant="ghost" size="sm" icon="eye" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500 py-8">
                            Keine Mobilgeräte gefunden.
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
