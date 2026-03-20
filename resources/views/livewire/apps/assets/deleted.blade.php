<?php

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Gelöschte Assets')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function assets(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return Asset::query()
            ->onlyTrashed()
            ->with(['type', 'vendor', 'owner', 'historyEntries.user'])
            ->when($this->search, function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('serial_number', 'like', $term)
                        ->orWhere('model', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('itexia_id', 'like', $term);
                });
            })
            ->orderByDesc('deleted_at')
            ->paginate(25);
    }

    public function restoreAsset(int $assetId): void
    {
        $asset = Asset::query()->onlyTrashed()->findOrFail($assetId);

        $asset->restore();
        $asset->historyEntries()->create([
            'event' => AssetHistory::EventRestored,
            'user_id' => auth()->id(),
            'reason' => 'Wiederherstellung über Gelöschte Assets',
        ]);

        Flux::toast('Asset wurde wiederhergestellt.', variant: 'success');
    }

    public function forceDeleteAsset(int $assetId): void
    {
        $asset = Asset::query()->onlyTrashed()->findOrFail($assetId);
        $asset->forceDelete();

        Flux::toast('Asset wurde endgültig gelöscht.', variant: 'success');
    }
}; ?>

<div>
    <x-intranet-app-assets::assets-layout heading="Gelöschte Assets" subheading="Archivierte Assets mit Verlauf und Aktionen">
        <div class="space-y-4">
            <div class="max-w-sm">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Suchen nach SN, Modell, Name, Itexia-ID..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Asset</flux:table.column>
                    <flux:table.column>Gelöscht am</flux:table.column>
                    <flux:table.column>Gelöscht von</flux:table.column>
                    <flux:table.column>Grund</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->assets as $asset)
                        @php
                            $deletedEntry = $asset->historyEntries
                                ->where('event', \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventDeleted)
                                ->sortByDesc('created_at')
                                ->first();
                        @endphp
                        <flux:table.row wire:key="deleted-asset-{{ $asset->id }}">
                            <flux:table.cell>
                                <div class="font-medium">{{ $asset->display_name }}</div>
                                <div class="text-xs text-zinc-500">{{ $asset->serial_number }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $asset->deleted_at?->format('d.m.Y H:i') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $deletedEntry?->user?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="max-w-md">{{ $deletedEntry?->reason ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:button href="{{ route('apps.assets.show', $asset) }}" variant="ghost" size="sm" icon="eye" />
                                    <flux:button
                                        wire:click="restoreAsset({{ $asset->id }})"
                                        wire:confirm="Asset wirklich wiederherstellen?"
                                        variant="primary"
                                        size="sm"
                                        icon="arrow-uturn-left"
                                    />
                                    <flux:button
                                        wire:click="forceDeleteAsset({{ $asset->id }})"
                                        wire:confirm="Asset endgültig löschen? Dieser Vorgang ist irreversibel."
                                        variant="danger"
                                        size="sm"
                                        icon="trash"
                                    />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center text-zinc-500 py-8">
                                Keine gelöschten Assets gefunden.
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
