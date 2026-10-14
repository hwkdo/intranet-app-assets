<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Support\ConfigmgrSerialMismatches;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    private function itemLimit(): int
    {
        $value = auth()->user()?->settings->app->assets->dashboard['widgetItemCounts']['falsche-seriennummern']
            ?? auth()->user()?->settings->dashboard->personalGrid?->widgetItemCounts['falsche-seriennummern']
            ?? 5;

        return min(max((int) $value, 1), 30);
    }

    public function mount(): void
    {
        $this->authorize('manage-app-assets');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Asset>
     */
    #[Computed]
    public function mismatches(): \Illuminate\Support\Collection
    {
        return ConfigmgrSerialMismatches::collect(
            Asset::query()->with(['type', 'vendor', 'owner']),
        )->sortByDesc(
            fn (Asset $asset): int => $asset->created_at?->getTimestamp() ?? 0,
        )->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Asset>
     */
    #[Computed]
    public function assets(): \Illuminate\Support\Collection
    {
        return $this->mismatches->take($this->itemLimit())->values();
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->totalCount() > $this->itemLimit();
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->mismatches->count();
    }
};
?>

@placeholder
    <flux:card class="h-full">
        <div class="mb-3 space-y-2">
            <flux:skeleton class="h-4 w-36" />
            <flux:skeleton class="h-3 w-56" />
        </div>
        <div class="space-y-2">
            <flux:skeleton class="h-14 w-full rounded-md" />
            <flux:skeleton class="h-14 w-full rounded-md" />
            <flux:skeleton class="h-14 w-full rounded-md" />
        </div>
    </flux:card>
@endplaceholder

<x-intranet-app-base::dashboard.widget-card
    :title="'Falsche Seriennummern ('.$this->totalCount().')'"
    :description="'Assets mit abweichender SCCM-Seriennummer (max. '.$this->itemLimit().')'"
>
    @forelse($this->assets as $asset)
        <a
            href="{{ route('apps.assets.show', [$asset, 'from' => 'falsche-seriennummern']) }}"
            wire:navigate
            class="group block cursor-pointer rounded-md border border-zinc-200 px-3 py-2 transition-colors duration-150 hover:bg-zinc-100 dark:border-zinc-600 dark:bg-zinc-800/40 dark:hover:bg-white/15"
        >
            <div class="font-medium group-hover:text-zinc-900 dark:group-hover:text-white">{{ $asset->display_name }}</div>
            <div class="text-xs text-zinc-500 dark:text-white">{{ $asset->serial_number }} · SCCM {{ $asset->configmgr_serial_number }}</div>
        </a>
    @empty
        <flux:text class="text-zinc-500 dark:text-white">Keine abweichenden SCCM-Seriennummern.</flux:text>
    @endforelse

    @if($this->hasMore)
        <div class="pt-1">
            <flux:button variant="ghost" size="sm" :href="route('apps.assets.admin.configmgr-serial-mismatches')" wire:navigate>
                Weitere anzeigen
            </flux:button>
        </div>
    @endif
</x-intranet-app-base::dashboard.widget-card>
