<?php

use Hwkdo\IntranetAppAssets\Models\Handover;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    private function itemLimit(): int
    {
        $value = auth()->user()?->settings->dashboard->personalGrid?->widgetItemCounts['abgelehnte-uebergaben'] ?? 5;

        return min(max((int) $value, 1), 30);
    }

    public function mount(): void
    {
        $this->authorize('manage-app-assets');
    }

    #[Computed]
    public function handovers(): \Illuminate\Database\Eloquent\Collection
    {
        return Handover::query()
            ->with(['asset.type', 'asset.vendor', 'recipient'])
            ->whereNotNull('rejected_at')
            ->orderByDesc('rejected_at')
            ->limit($this->itemLimit())
            ->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->totalCount() > $this->itemLimit();
    }

    #[Computed]
    public function totalCount(): int
    {
        return Handover::query()
            ->whereNotNull('rejected_at')
            ->count();
    }
};
?>

@placeholder
    <flux:card class="h-full">
        <div class="mb-3 space-y-2">
            <flux:skeleton class="h-4 w-44" />
            <flux:skeleton class="h-3 w-64" />
        </div>
        <div class="space-y-2">
            <flux:skeleton class="h-14 w-full rounded-md" />
            <flux:skeleton class="h-14 w-full rounded-md" />
            <flux:skeleton class="h-14 w-full rounded-md" />
        </div>
    </flux:card>
@endplaceholder

<x-intranet-app-base::dashboard.widget-card
    :title="'Abgelehnte Übergaben ('.$this->totalCount().')'"
    :description="'Abgelehnte Übergaben (max. '.$this->itemLimit().')'"
>
    @forelse($this->handovers as $handover)
        <a
            href="{{ route('apps.assets.admin.rejected-handover.resolve', $handover) }}"
            wire:navigate
            class="group block cursor-pointer rounded-md border border-zinc-200 px-3 py-2 transition-colors duration-150 hover:bg-zinc-100 dark:border-zinc-600 dark:bg-zinc-800/40 dark:hover:bg-white/15"
        >
            <div class="font-medium group-hover:text-zinc-900 dark:group-hover:text-white">
                {{ $handover->asset?->display_name ?? 'Unbekanntes Asset' }}
            </div>
            <div class="text-xs text-zinc-500 dark:text-white">
                {{ $handover->asset?->serial_number ?? '—' }}
            </div>
        </a>
    @empty
        <flux:text class="text-zinc-500 dark:text-white">Keine abgelehnten Übergaben vorhanden.</flux:text>
    @endforelse

    @if($this->hasMore)
        <div class="pt-1">
            <flux:button variant="ghost" size="sm" :href="route('apps.assets.admin.rejected-handovers')" wire:navigate>
                Weitere anzeigen
            </flux:button>
        </div>
    @endif
</x-intranet-app-base::dashboard.widget-card>
