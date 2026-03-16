<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

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

    #[Computed]
    public function assets(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return Asset::query()
            ->with(['type', 'vendor', 'owner'])
            ->whereHas('type', fn ($q) => $q->where('is_intune_object', true))
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
            ->orderBy('model')
            ->paginate(25);
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
