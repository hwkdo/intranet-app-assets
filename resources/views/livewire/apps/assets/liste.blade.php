<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Alle Assets')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $statusFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function assets(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return Asset::query()
            ->with(['type', 'vendor', 'owner'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('serial_number', 'like', "%{$this->search}%")
                        ->orWhere('model', 'like', "%{$this->search}%")
                        ->orWhere('name', 'like', "%{$this->search}%")
                        ->orWhere('itexia_id', 'like', "%{$this->search}%");
                });
            })
            ->when($this->typeFilter, fn ($q) => $q->where('asset_type_id', $this->typeFilter))
            ->when($this->statusFilter === 'missing', fn ($q) => $q->where('is_missing', true))
            ->when($this->statusFilter === 'clarification', fn ($q) => $q->where('is_clarification', true))
            ->orderBy('model')
            ->paginate(25);
    }

    #[Computed]
    public function assetTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return AssetType::allOrdered();
    }
}; ?>
<div>
<x-intranet-app-assets::assets-layout heading="Alle Assets" subheading="Übersicht aller verwalteten Assets">
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 gap-3">
                <div class="flex-1 max-w-sm">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suchen nach SN, Modell, Name, Itexia-ID…"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>
                <flux:select wire:model.live="typeFilter" placeholder="Alle Typen" class="w-44">
                    <flux:select.option value="">Alle Typen</flux:select.option>
                    @foreach($this->assetTypes as $type)
                        <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="statusFilter" placeholder="Alle Status" class="w-44">
                    <flux:select.option value="">Alle Status</flux:select.option>
                    <flux:select.option value="missing">Vermisst</flux:select.option>
                    <flux:select.option value="clarification">In Klärung</flux:select.option>
                </flux:select>
            </div>
            @can('manage-app-assets')
                <flux:button href="{{ route('apps.assets.create') }}" variant="primary" icon="plus">
                    Neues Asset
                </flux:button>
            @endcan
        </div>

        <flux:table>
            <flux:table.columns>
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
                            <flux:button href="{{ route('apps.assets.show', $asset) }}" variant="ghost" size="sm" icon="eye" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-500 py-8">
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