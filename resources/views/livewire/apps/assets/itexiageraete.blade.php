<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Itexia-Geräte')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var 'all'|'found'|'not-found' */
    #[Url]
    public string $itexiaFilter = 'all';

    /** @var 'all'|'without'|'with' */
    #[Url]
    public string $invoiceOrderFilter = 'all';

    #[Url]
    public string $typeFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedItexiaFilter(): void
    {
        $this->resetPage();
    }

    public function updatedInvoiceOrderFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function assetTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return AssetType::allOrdered();
    }

    #[Computed]
    public function assets(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $searchTerm = trim($this->search ?? '');
        return Asset::query()
            ->with(['type', 'vendor', 'owner'])
            ->whereNotNull('itexia_id')
            ->where('itexia_id', '!=', '')
            ->when($searchTerm !== '', function ($query) use ($searchTerm) {
                $term = '%'.$searchTerm.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('serial_number', 'like', $term)
                        ->orWhere('model', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('itexia_id', 'like', $term)
                        ->orWhere('itexia_uuid', 'like', $term)
                        ->orWhere('invoice_number', 'like', $term)
                        ->orWhere('order_number', 'like', $term)
                        ->orWhereHas('type', fn ($t) => $t->where('name', 'like', $term))
                        ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $term))
                        ->orWhereHas('owner', fn ($o) => $o->where('name', 'like', $term));
                });
            })
            ->when($this->itexiaFilter === 'found', fn ($q) => $q->whereNotNull('itexia_uuid')->where('itexia_uuid', '!=', ''))
            ->when($this->itexiaFilter === 'not-found', fn ($q) => $q->where(function ($q) {
                $q->whereNull('itexia_uuid')->orWhere('itexia_uuid', '');
            }))
            ->when($this->invoiceOrderFilter === 'without', fn ($q) => $q->where(function ($q) {
                $q->where(function ($q) {
                    $q->whereNull('invoice_number')->orWhere('invoice_number', '');
                })->where(function ($q) {
                    $q->whereNull('order_number')->orWhere('order_number', '');
                });
            }))
            ->when($this->invoiceOrderFilter === 'with', fn ($q) => $q->where(function ($q) {
                $q->whereNotNull('invoice_number')->where('invoice_number', '!=', '')
                    ->orWhereNotNull('order_number')->where('order_number', '!=', '');
            }))
            ->when($this->typeFilter, fn ($q) => $q->where('asset_type_id', $this->typeFilter))
            ->orderBy('model')
            ->paginate(25);
    }
}; ?>
<div>
<x-intranet-app-assets::assets-layout heading="Itexia-Geräte" subheading="Assets mit Itexia-ID (Barcode) und Seventhings-UUID">
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 flex-wrap items-center gap-3">
                <div class="min-w-64 max-w-sm flex-1">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suchen in allen Spalten…"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>
                <flux:select wire:model.live="typeFilter" placeholder="Alle Typen" class="w-44 shrink-0">
                    <flux:select.option value="">Alle Typen</flux:select.option>
                    @foreach($this->assetTypes as $type)
                        <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="itexiaFilter" placeholder="Itexia-Filter" class="w-52 shrink-0">
                    <flux:select.option value="all">Alle Itexia-Geräte</flux:select.option>
                    <flux:select.option value="found">Gefunden in Itexia</flux:select.option>
                    <flux:select.option value="not-found">Nicht gefunden in Itexia</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="invoiceOrderFilter" placeholder="Rechnung/Bestellung" class="w-52 shrink-0">
                    <flux:select.option value="all">Alle</flux:select.option>
                    <flux:select.option value="without">Ohne Rechnung/Bestellung</flux:select.option>
                    <flux:select.option value="with">Mit Rechnung/Bestellung</flux:select.option>
                </flux:select>
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Modell / Name</flux:table.column>
                <flux:table.column>Seriennummer</flux:table.column>
                <flux:table.column>Itexia-ID</flux:table.column>
                <flux:table.column>Itexia-UUID</flux:table.column>
                <flux:table.column>Rechnungsnr.</flux:table.column>
                <flux:table.column>BEN</flux:table.column>
                <flux:table.column>Typ</flux:table.column>
                <flux:table.column>Hersteller</flux:table.column>
                <flux:table.column>Besitzer</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->assets as $asset)
                    <flux:table.row wire:key="itexia-{{ $asset->id }}">
                        <flux:table.cell>
                            <div class="font-medium">{{ $asset->display_name }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $asset->serial_number }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $asset->itexia_id ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm max-w-[12rem]">
                            @php $uuid = $asset->itexia_uuid ?? '—'; @endphp
                            <flux:tooltip :content="$uuid" position="top">
                                <span class="block truncate">{{ $uuid }}</span>
                            </flux:tooltip>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $asset->invoice_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $asset->order_number ?? '—' }}</flux:table.cell>
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
                                @if($asset->itexia_uuid)
                                    <flux:badge color="green" size="sm">Gefunden</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm">Nicht gefunden</flux:badge>
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
                        <flux:table.cell colspan="11" class="text-center text-zinc-500 py-8">
                            Keine Itexia-Geräte gefunden.
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
