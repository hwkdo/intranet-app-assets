@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => [],
    'renderAppIndexAuto' => null,
])

@php
    $navItems = ! empty($navItems) ? $navItems : \Hwkdo\IntranetAppAssets\Support\AssetNavigation::items();
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('assets');
    $canUseAi = \Hwkdo\IntranetAppBase\Support\AiUsage::allowed();
    $currentPath = trim(request()->path(), '/');
    $activeHref = \Hwkdo\IntranetAppAssets\Support\AssetNavigation::activeHref($navItems, $currentPath);
@endphp

@if ($customBgUrl)
    @push('app-styles')
        <style data-app-bg data-ts="{{ uniqid() }}">
            :root {
                --app-bg-image: url('{{ $customBgUrl }}');
            }
        </style>
    @endpush
@endif

<div class="hwk-assets space-y-4">
    <style>
        .hwk-assets {
            --color-accent: #073070;
            --color-accent-content: #073070;
            --color-accent-foreground: #ffffff;
        }

        .dark .hwk-assets {
            --color-accent: #d0e3f9;
            --color-accent-content: #d0e3f9;
            --color-accent-foreground: #04214e;
        }

        .hwk-assets ui-tabs.hwk-pills {
            height: auto !important;
            flex-wrap: wrap;
            gap: 0.375rem;
            border-bottom-width: 0 !important;
        }

        .hwk-assets ui-tabs.hwk-pills [data-flux-tab] {
            height: auto;
            margin-bottom: 0 !important;
            border-bottom-width: 0 !important;
            border-radius: 9999px;
            padding: 0.375rem 0.75rem;
            background: rgb(255 255 255 / 0.8) !important;
            color: #073070 !important;
            box-shadow: inset 0 0 0 1px #d0e3f9;
        }

        .dark .hwk-assets ui-tabs.hwk-pills [data-flux-tab] {
            background: rgb(255 255 255 / 0.1) !important;
            color: #fff !important;
            box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.15);
        }

        .hwk-assets ui-tabs.hwk-pills [data-flux-tab][data-selected],
        .dark .hwk-assets ui-tabs.hwk-pills [data-flux-tab][data-selected] {
            background: #073070 !important;
            color: #fff !important;
            box-shadow: 0 4px 6px rgb(7 48 112 / 0.25);
        }

        .hwk-assets [data-flux-heading] {
            color: #073070;
        }

        .dark .hwk-assets [data-flux-heading] {
            color: #fff;
        }

        .hwk-assets dl.hwk-rows {
            display: grid;
            grid-template-columns: minmax(8rem, 14rem) minmax(0, 1fr);
            column-gap: 1.5rem;
        }

        .hwk-assets dl.hwk-rows > dt,
        .hwk-assets dl.hwk-rows > dd {
            border-bottom: 1px solid #d0e3f9;
            padding-block: 0.7rem;
            font-size: 0.875rem;
        }

        .dark .hwk-assets dl.hwk-rows > dt,
        .dark .hwk-assets dl.hwk-rows > dd {
            border-bottom-color: rgb(255 255 255 / 0.1);
        }

        .hwk-assets dl.hwk-rows > dt {
            font-weight: 500;
            color: #073070;
        }

        .dark .hwk-assets dl.hwk-rows > dt {
            color: #fff;
        }

        .hwk-assets dl.hwk-rows > dd {
            color: var(--color-slate-600);
        }

        .dark .hwk-assets dl.hwk-rows > dd {
            color: rgb(255 255 255 / 0.7);
        }

        .hwk-assets form.hwk-fields .grid {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .hwk-assets form.hwk-fields [data-flux-field] {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 0.35rem 1.5rem;
            align-items: center;
            border-bottom: 1px solid #d0e3f9;
            padding-block: 0.9rem;
        }

        .hwk-assets form.hwk-fields [data-flux-field] > * {
            grid-column: 1;
            min-width: 0;
        }

        .hwk-assets form.hwk-fields [data-flux-field] > [data-flux-label] {
            margin-bottom: 0 !important;
        }

        .dark .hwk-assets form.hwk-fields [data-flux-field] {
            border-bottom-color: rgb(255 255 255 / 0.1);
        }

        @media (min-width: 640px) {
            .hwk-assets form.hwk-fields [data-flux-field] {
                grid-template-columns: minmax(9rem, 16rem) minmax(0, 1fr);
            }

            .hwk-assets form.hwk-fields [data-flux-field] > * {
                grid-column: 2;
            }

            .hwk-assets form.hwk-fields [data-flux-field] > [data-flux-label] {
                grid-column: 1;
                grid-row: 1;
            }
        }

        .hwk-assets [data-flux-card] {
            background-color: rgb(255 255 255 / var(--glass-opacity)) !important;
            border: 1px solid rgb(208 227 249 / 0.8) !important;
            color: var(--color-slate-800);
            box-shadow: none !important;
            backdrop-filter: blur(8px);
        }

        .dark .hwk-assets [data-flux-card] {
            background-color: rgb(7 48 112 / var(--glass-opacity)) !important;
            border-color: rgb(255 255 255 / 0.08) !important;
            color: rgb(255 255 255 / 0.92);
        }
    </style>

    <x-intranet-app-assets::hwk-hero
        eyebrow="Assets"
        :title="$heading !== '' ? $heading : 'Assets'"
        :description="$subheading !== '' ? $subheading : null"
    >
        @isset($actions)
            <x-slot:actions>{{ $actions }}</x-slot:actions>
        @endisset
    </x-intranet-app-assets::hwk-hero>

    <div class="flex items-start gap-6 max-lg:flex-col">
        <nav
            aria-label="Assets"
            class="glass-card w-full shrink-0 p-2 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:w-64 lg:overflow-y-auto"
            style="--glass-opacity: 0.92; overflow: auto"
        >
            <ul class="space-y-0.5">
                @foreach ($navItems as $navItem)
                    @if (($navItem['type'] ?? '') === 'separator')
                        @if (! isset($navItem['permission']) || auth()->user()?->can($navItem['permission']))
                            <li class="px-3 pt-3 pb-1 text-xs font-semibold tracking-[0.2em] text-[#073070]/70 uppercase dark:text-white/60">{{ $navItem['label'] }}</li>
                        @endif
                    @elseif (! isset($navItem['permission']) || auth()->user()?->can($navItem['permission']))
                        <li>
                            @if (! empty($navItem['requiresAiUsage']) && ! $canUseAi)
                                <x-intranet-app-base::ai-usage-locked-nav-item :label="$navItem['label']" />
                            @else
                                @php
                                    $current = $activeHref !== null && $activeHref === ($navItem['href'] ?? null);
                                    $badgeAmber = ($navItem['badgeColor'] ?? 'red') === 'amber';
                                @endphp
                                <a
                                    href="{{ $navItem['href'] }}"
                                    wire:navigate
                                    @class([
                                        'flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070]',
                                        'bg-[#073070] text-white shadow-md shadow-[#073070]/25' => $current,
                                        'text-[#073070] hover:bg-[#d0e3f9]/80 dark:text-white dark:hover:bg-white/10' => ! $current,
                                    ])
                                    @if ($current) aria-current="page" @endif
                                >
                                    @if (! empty($navItem['icon']))
                                        <flux:icon :icon="$navItem['icon']" variant="micro" class="size-4 shrink-0" />
                                    @endif
                                    <span class="min-w-0 flex-1 truncate">{{ $navItem['label'] }}</span>
                                    @if (isset($navItem['badge']) && $navItem['badge'] !== null && $navItem['badge'] !== '')
                                        <span @class([
                                            'inline-flex min-w-5 shrink-0 justify-center rounded-full px-1.5 text-xs font-semibold text-white',
                                            'bg-amber-500' => $badgeAmber,
                                            'bg-red-600' => ! $badgeAmber,
                                        ])>{{ $navItem['badge'] }}</span>
                                    @endif
                                </a>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0 flex-1 space-y-4">
            {{ $slot }}
        </div>
    </div>
</div>

@push('nav-items')
    @json($navItems)
@endpush
