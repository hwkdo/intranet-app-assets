<?php

use Hwkdo\IntranetAppAssets\Models\AssetPermanentDeletionArchive;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Archiv endgültig gelöschter Assets')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function archives(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return AssetPermanentDeletionArchive::query()
            ->with('deletedBy')
            ->when($this->search !== '', function ($query): void {
                $raw = trim($this->search);
                $term = '%'.$raw.'%';
                $query->where(function ($q) use ($term, $raw): void {
                    $q->where('payload->serial_number', 'like', $term)
                        ->orWhere('payload->model', 'like', $term)
                        ->orWhere('payload->name', 'like', $term)
                        ->orWhere('payload->itexia_id', 'like', $term)
                        ->orWhere('payload->order_number', 'like', $term)
                        ->orWhere('payload->legacy_id', 'like', $term);

                    if ($raw !== '' && ctype_digit($raw)) {
                        $q->orWhere('original_asset_id', (int) $raw);
                    }
                });
            })
            ->orderByDesc('archived_at')
            ->paginate(25);
    }
}; ?>

<div>
    <x-intranet-app-assets::assets-layout
        heading="Archiv endgültig gelöschter Assets"
        subheading="Metadaten-Snapshots von Assets nach endgültiger Löschung (z. B. aus „Gelöschte Assets“)"
    >
        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button
                    href="{{ route('apps.assets.deleted') }}"
                    variant="ghost"
                    size="sm"
                    icon="arrow-left"
                    wire:navigate
                >
                    Zurück zu gelöschten Assets
                </flux:button>
                <div class="max-w-sm flex-1 min-w-[12rem]">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suche: ehem. ID, SN, Modell, Name, Itexia, BEN, Legacy-ID…"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Ehem. ID</flux:table.column>
                    <flux:table.column>Asset</flux:table.column>
                    <flux:table.column>Seriennummer</flux:table.column>
                    <flux:table.column>BEN</flux:table.column>
                    <flux:table.column>Itexia-ID</flux:table.column>
                    <flux:table.column>Typ / Hersteller</flux:table.column>
                    <flux:table.column>Endgültig am</flux:table.column>
                    <flux:table.column>Von</flux:table.column>
                    <flux:table.column>Quelle</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->archives as $archive)
                        @php
                            $p = $archive->payload ?? [];
                        @endphp
                        <flux:table.row wire:key="perm-del-archive-{{ $archive->id }}">
                            <flux:table.cell class="font-mono text-sm">{{ $archive->original_asset_id }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium">{{ $archive->displayNameFromPayload() }}</div>
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-sm">{{ $p['serial_number'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-sm">{{ filled($p['order_number'] ?? null) ? $p['order_number'] : '—' }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-sm">{{ filled($p['itexia_id'] ?? null) ? $p['itexia_id'] : '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="text-sm">{{ $p['asset_type_name'] ?? '—' }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $p['asset_vendor_name'] ?? '—' }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $archive->archived_at?->format('d.m.Y H:i') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $archive->deletedBy?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-600 dark:text-zinc-300">{{ $archive->source }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9" class="text-center text-zinc-500 py-8">
                                Keine archivierten endgültigen Löschungen.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <div>
                {{ $this->archives->links() }}
            </div>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
