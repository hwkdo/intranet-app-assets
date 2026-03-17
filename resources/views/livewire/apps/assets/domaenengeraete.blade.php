<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

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

    #[Computed]
    public function assets(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $orderColumn = in_array($this->sortBy, ['domain_last_seen', 'last_logon_timestamp'], true)
            ? $this->sortBy
            : 'model';
        $orderDir = $this->sortDir === 'asc' ? 'asc' : 'desc';

        return Asset::query()
            ->with(['type', 'vendor', 'owner'])
            ->whereHas('type', fn ($q) => $q->where('is_domain_object', true))
            ->when($this->search, function ($query) {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('serial_number', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('itexia_id', 'like', "%{$search}%")
                        ->orWhere('domain_connection', 'like', "%{$search}%")
                        ->orWhere('smbios_guid', 'like', "%{$search}%")
                        ->orWhere('configmgr_last_logon_user', 'like', "%{$search}%")
                        ->orWhere('configmgr_mac_addresses', 'like', "%{$search}%");
                });
            })
            ->orderBy($orderColumn, $orderDir)
            ->paginate(25);
    }
}; ?>
<div>
<x-intranet-app-assets::assets-layout heading="Domänengeräte" subheading="Assets vom Typ Domänengerät (is_domain_object)">
    <div class="space-y-4">
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
