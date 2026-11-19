<?php

use Hwkdo\IntranetAppAssets\Exports\AssetsTableExport;
use Hwkdo\IntranetAppAssets\Livewire\Concerns\SortsAssetTable;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Services\AssetLocationDisplayResolver;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')] #[Title('Mobilgeräte')] class extends Component
{
    use SortsAssetTable;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var 'all'|'intune'|'non-intune' */
    #[Url]
    public string $intuneFilter = 'all';

    /** @var list<int|string> */
    #[Url(as: 'types')]
    public array $typeIds = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedIntuneFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeIds(): void
    {
        $this->resetPage();
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['display_name', 'serial_number', 'imei', 'type', 'vendor', 'owner', 'status', 'created_at'];
    }

    /**
     * @return list<int>
     */
    private function validatedMobilTypeIdsForQuery(): array
    {
        $allowed = AssetType::query()
            ->where('is_intune_object', true)
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
        $selected = array_values(array_unique(array_filter(array_map('intval', $this->typeIds))));

        return array_values(array_intersect($selected, $allowed));
    }

    protected function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Asset::query()
            ->with(['type', 'vendor', 'owner.standort'])
            ->whereHas('type', fn ($q) => $q->where('is_intune_object', true));
    }

    #[Computed]
    public function intuneAssetTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return AssetType::query()->where('is_intune_object', true)->orderBy('name')->get();
    }

    #[Computed]
    public function assets(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $mobilTypeIds = $this->validatedMobilTypeIdsForQuery();

        $query = $this->baseQuery()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $term = '%'.$this->search.'%';
                    $q->where('serial_number', 'like', $term)
                        ->orWhere('model', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('itexia_id', 'like', $term)
                        ->orWhere('imei', 'like', $term)
                        ->orWhereHas('owner', fn ($o) => $o->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term));
                });
            })
            ->when($mobilTypeIds !== [], fn ($q) => $q->whereIn('asset_type_id', $mobilTypeIds))
            ->when($this->intuneFilter === 'intune', fn ($q) => $q->whereNotNull('intune_device_id'))
            ->when($this->intuneFilter === 'non-intune', fn ($q) => $q->whereNull('intune_device_id'));

        return $this->applyAssetSort($query)->paginate(25);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Asset>
     */
    public function getExportQueryAll(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->baseQuery()->orderBy('model')->orderBy('id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Asset>
     */
    public function getExportQueryFiltered(): \Illuminate\Database\Eloquent\Builder
    {
        $mobilTypeIds = $this->validatedMobilTypeIdsForQuery();

        return $this->baseQuery()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $term = '%'.$this->search.'%';
                    $q->where('serial_number', 'like', $term)
                        ->orWhere('model', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('itexia_id', 'like', $term)
                        ->orWhere('imei', 'like', $term)
                        ->orWhereHas('owner', fn ($o) => $o->where('vorname', 'like', $term)->orWhere('nachname', 'like', $term));
                });
            })
            ->when($mobilTypeIds !== [], fn ($q) => $q->whereIn('asset_type_id', $mobilTypeIds))
            ->when($this->intuneFilter === 'intune', fn ($q) => $q->whereNotNull('intune_device_id'))
            ->when($this->intuneFilter === 'non-intune', fn ($q) => $q->whereNull('intune_device_id'))
            ->orderBy('model')
            ->orderBy('id');
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
            ['heading' => 'Standort', 'value' => fn (Asset $a) => AssetLocationDisplayResolver::resolve($a)['value'] ?? '—'],
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
            ['heading' => 'Angelegt', 'value' => fn (Asset $a) => $a->created_at?->timezone((string) config('app.timezone'))->format('d.m.Y H:i') ?? '—'],
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
        <div class="glass-card sticky top-3 z-20 p-3 shadow-lg shadow-[#073070]/10 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" style="--glass-opacity: 0.92; overflow: visible">
            <div class="flex flex-1 gap-3">
                <div class="flex-1 max-w-sm">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suchen nach SN, Modell, Name, IMEI…"
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
                    class="min-w-52 max-w-md shrink-0 [&_[data-flux-pillbox-placeholder]]:text-[#073070] dark:[&_[data-flux-pillbox-placeholder]]:text-white/70"
                >
                    @foreach($this->intuneAssetTypes as $type)
                        <flux:pillbox.option value="{{ $type->id }}">{{ $type->name }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
                <flux:select wire:model.live="intuneFilter" placeholder="Intune-Filter" class="w-52 shrink-0">
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

        <div class="glass-card overflow-hidden">
<flux:table>
            <flux:table.columns>
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'display_name', 'label' => 'Modell / Name'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'serial_number', 'label' => 'Seriennummer'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'imei', 'label' => 'IMEI'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'type', 'label' => 'Typ'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'vendor', 'label' => 'Hersteller'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'owner', 'label' => 'Besitzer'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'status', 'label' => 'Status'])
                @include('intranet-app-assets::partials.sortable-column', ['column' => 'created_at', 'label' => 'Angelegt', 'align' => 'end'])
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->assets as $asset)
                    <flux:table.row wire:key="mobile-{{ $asset->id }}">
                        <flux:table.cell>
                            <div class="font-medium">{{ $asset->display_name }}</div>
                            @if($asset->itexia_id)
                                <div class="text-xs text-slate-600 dark:text-white/70">{{ $asset->itexia_id }}</div>
                            @endif
                        </flux:table.cell>
                        @include('intranet-app-assets::partials.serial-number-cell', ['serialNumber' => $asset->serial_number])
                        <flux:table.cell class="font-mono text-sm">{{ $asset->imei ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->type?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->vendor?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->owner?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @if($asset->intune_device_id)
                                    <x-intranet-app-assets::hwk-badge color="blue" size="sm">Intune</x-intranet-app-assets::hwk-badge>
                                @else
                                    <x-intranet-app-assets::hwk-badge color="zinc" size="sm">Nicht-Intune</x-intranet-app-assets::hwk-badge>
                                @endif
                                @if($asset->is_missing)
                                    <x-intranet-app-assets::hwk-badge color="red" size="sm">Vermisst</x-intranet-app-assets::hwk-badge>
                                @endif
                                @if($asset->is_clarification)
                                    <x-intranet-app-assets::hwk-badge color="amber" size="sm">Klärung</x-intranet-app-assets::hwk-badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        @include('intranet-app-assets::partials.created-at-cell', ['asset' => $asset])
                        <flux:table.cell>
                            <flux:button href="{{ route('apps.assets.show', [$asset, 'from' => 'mobilgeraete']) }}" variant="ghost" size="sm" icon="eye" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="px-3 py-3">
<x-intranet-app-assets::hwk-empty bare icon="magnifying-glass" title="Keine Mobilgeräte gefunden." />
</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
</div>

        <div>
            <div class="glass-card px-4 py-3" style="--glass-opacity: 0.92">
                {{ $this->assets->links() }}
            </div>
        </div>
    </div>
</x-intranet-app-assets::assets-layout>
</div>
