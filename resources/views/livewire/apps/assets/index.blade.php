<?php

use Hwkdo\IntranetAppAssets\Support\AssetNavigation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Assets')] class extends Component {
    public function groups(): array
    {
        $items = array_values(array_filter(
            AssetNavigation::items(),
            fn (array $item): bool => ! isset($item['permission']) || (bool) auth()->user()?->can($item['permission']),
        ));

        return array_values(array_filter(
            AssetNavigation::groups($items),
            fn (array $group): bool => $group['items'] !== [],
        ));
    }
};
?>
<div>
<x-intranet-app-assets::assets-layout heading="Assets" subheading="Asset-Verwaltung">
    <div class="space-y-4">
        @foreach ($this->groups() as $group)
            <section class="glass-card overflow-hidden">
                <div class="border-b border-[#d0e3f9] px-5 py-4 dark:border-white/10">
                    <h2 class="text-lg font-semibold text-[#073070] dark:text-white">{{ $group['label'] }}</h2>
                </div>
                <div class="grid gap-px bg-[#d0e3f9] sm:grid-cols-2 dark:bg-white/10">
                    @foreach ($group['items'] as $item)
                        <a href="{{ $item['href'] }}" wire:navigate class="flex items-start gap-3 bg-white/70 px-5 py-4 transition hover:bg-[#d0e3f9]/40 dark:bg-white/5 dark:hover:bg-white/10">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                                <flux:icon icon="{{ $item['icon'] }}" variant="micro" class="size-5" />
                            </span>
                            <span class="min-w-0">
                                <span class="block font-medium text-[#073070] dark:text-white">{{ $item['label'] }}</span>
                                @if (filled($item['description'] ?? null))
                                    <span class="mt-0.5 block text-sm text-slate-600 dark:text-white/70">{{ $item['description'] }}</span>
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-intranet-app-assets::assets-layout>
</div>
