<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    private function itemLimit(): int
    {
        $value = auth()->user()?->settings->app->assets->dashboard['widgetItemCounts']['vermisste-assets']
            ?? auth()->user()?->settings->dashboard->personalGrid?->widgetItemCounts['vermisste-assets']
            ?? 5;

        return min(max((int) $value, 1), 30);
    }

    public function mount(): void
    {
        $this->authorize('manage-app-assets');
    }

    #[Computed]
    public function assets(): \Illuminate\Database\Eloquent\Collection
    {
        return Asset::query()
            ->with(['type', 'vendor', 'owner'])
            ->where('is_missing', true)
            ->orderByDesc('created_at')
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
        return Asset::query()
            ->where('is_missing', true)
            ->count();
    }
};
?>

@placeholder
    <section class="glass-card overflow-hidden h-full">
        <div class="mb-3 space-y-2">
            <flux:skeleton class="h-4 w-36" />
            <flux:skeleton class="h-3 w-56" />
        </div>
        <div class="space-y-2">
            <flux:skeleton class="h-14 w-full rounded-md" />
            <flux:skeleton class="h-14 w-full rounded-md" />
            <flux:skeleton class="h-14 w-full rounded-md" />
        </div>
    </section>
@endplaceholder

<x-intranet-app-base::dashboard.widget-card
    :title="'Vermisste Assets ('.$this->totalCount().')'"
    :description="'Assets mit Status Vermisst (max. '.$this->itemLimit().')'"
>
    @forelse($this->assets as $asset)
        <a
            href="{{ route('apps.assets.show', [$asset, 'from' => 'liste']) }}"
            wire:navigate
            class="group block cursor-pointer rounded-md border border-[#d0e3f9] px-3 py-2 transition-colors duration-150 hover:bg-[#d0e3f9]/70 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/15"
        >
            <div class="font-medium group-hover:text-[#073070] dark:group-hover:text-white">{{ $asset->display_name }}</div>
            <div class="text-xs text-slate-600 dark:text-white">{{ $asset->serial_number ?? '—' }}</div>
        </a>
    @empty
        <x-intranet-app-assets::hwk-empty bare icon="inbox" title="Keine vermissten Assets vorhanden." />
    @endforelse

    @if($this->hasMore)
        <div class="pt-1">
            <flux:button variant="ghost" size="sm" :href="route('apps.assets.admin.missing')" wire:navigate>
                Weitere anzeigen
            </flux:button>
        </div>
    @endif
</x-intranet-app-base::dashboard.widget-card>
