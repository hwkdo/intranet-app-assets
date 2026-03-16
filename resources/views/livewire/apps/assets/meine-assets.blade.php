<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Meine Assets')] class extends Component
{
    #[Computed]
    public function assets(): \Illuminate\Database\Eloquent\Collection
    {
        return Asset::query()
            ->with(['type', 'vendor'])
            ->withCount('handovers')
            ->where('user_id', auth()->id())
            ->orderBy('model')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, Handover> keyed by asset_id */
    #[Computed]
    public function pendingHandoversByAssetId(): \Illuminate\Support\Collection
    {
        $assetIds = $this->assets->pluck('id');
        if ($assetIds->isEmpty()) {
            return collect();
        }

        return Handover::query()
            ->where('recipient_user_id', auth()->id())
            ->whereNull('confirmed_at')
            ->whereIn('asset_id', $assetIds)
            ->get()
            ->keyBy('asset_id');
    }
}; ?>
<div>
    <x-intranet-app-assets::assets-layout heading="Meine Assets" subheading="Übersicht Ihrer zugewiesenen Assets">
        <div class="space-y-4">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Modell / Name</flux:table.column>
                    <flux:table.column>Seriennummer</flux:table.column>
                    <flux:table.column>Typ</flux:table.column>
                    <flux:table.column>Hersteller</flux:table.column>
                    <flux:table.column>Übergabe</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->assets as $asset)
                        <flux:table.row wire:key="asset-{{ $asset->id }}">
                            <flux:table.cell>
                                <div class="font-medium">{{ $asset->display_name }}</div>
                                @if($asset->itexia_id)
                                    <div class="text-xs text-zinc-500">{{ $asset->itexia_id }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-sm">{{ $asset->serial_number }}</flux:table.cell>
                            <flux:table.cell>{{ $asset->type?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="max-w-[10rem]">
                                @php $vendorName = $asset->vendor?->name ?? '—'; @endphp
                                <flux:tooltip :content="$vendorName" position="top">
                                    <span class="block truncate">{{ $vendorName }}</span>
                                </flux:tooltip>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($asset->handovers_count > 0)
                                    <flux:badge color="zinc" size="sm">{{ $asset->handovers_count }} {{ $asset->handovers_count === 1 ? 'Übergabe' : 'Übergaben' }}</flux:badge>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @php $pendingHandover = $this->pendingHandoversByAssetId->get($asset->id); @endphp
                                @if($pendingHandover)
                                    <flux:button href="{{ route('apps.assets.handover.confirm', $pendingHandover) }}" variant="primary" size="sm" icon="check-circle">
                                        Übergabe bestätigen
                                    </flux:button>
                                @endif
                                <flux:button href="{{ route('apps.assets.show', $asset) }}" variant="ghost" size="sm" icon="eye">
                                    Detail
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center text-zinc-500 py-8">
                                Ihnen sind derzeit keine Assets zugewiesen.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
