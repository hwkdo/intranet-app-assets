<?php

use App\Models\User;
use Hwkdo\IntranetAppBase\Services\DashboardGridLayoutService;
use Hwkdo\IntranetAppBase\Services\DashboardWidgetRegistry;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Assets Dashboard')] class extends Component
{
    /** @var array<int, array{key: string, title: string, description: string, component: string, defaultW: int, defaultH: int, minW: int, minH: int, defaultEnabled: bool, mandatory: bool}> */
    public array $availableWidgets = [];

    /** @var list<string> */
    public array $enabledWidgets = [];

    /** @var array<int, array{widgetKey: string, x: int, y: int, w: int, h: int}> */
    public array $layout = [];

    public function mount(DashboardWidgetRegistry $registry, DashboardGridLayoutService $gridLayoutService): void
    {
        /** @var User $user */
        $user = auth()->user();

        $definitions = $registry->widgetsForApp('assets', $user);
        $this->availableWidgets = array_map(static function ($definition): array {
            return [
                'key' => $definition->key,
                'title' => $definition->title,
                'description' => $definition->description,
                'component' => $definition->component,
                'defaultW' => $definition->defaultW,
                'defaultH' => $definition->defaultH,
                'minW' => $definition->minW,
                'minH' => $definition->minH,
                'defaultEnabled' => $definition->defaultEnabled,
                'mandatory' => $definition->mandatory,
            ];
        }, $definitions);

        $widgetMeta = $gridLayoutService->widgetMetaByKeyFromDefinitions($definitions);
        $allowedLookup = array_fill_keys(array_keys($widgetMeta), true);

        $settings = $user->settings->app->assets->dashboard ?? [];
        $savedEnabled = Arr::wrap($settings['enabledWidgets'] ?? []);
        $savedLayout = Arr::wrap($settings['layout'] ?? []);
        $defaultEnabled = collect($this->availableWidgets)
            ->filter(static fn (array $widget): bool => $widget['defaultEnabled'] === true)
            ->pluck('key')
            ->values()
            ->all();

        $this->enabledWidgets = $gridLayoutService->sanitizeEnabledWidgets(
            $savedEnabled !== [] ? $savedEnabled : $defaultEnabled,
            $allowedLookup,
            [],
        );
        $this->layout = $gridLayoutService->normalizeLayout(
            $widgetMeta,
            $this->enabledWidgets,
            $savedLayout,
        );
        $this->persistDashboardSettings();
    }

    public function toggleWidget(string $widgetKey): void
    {
        if (! in_array($widgetKey, $this->availableWidgetKeys(), true)) {
            return;
        }

        if (in_array($widgetKey, $this->enabledWidgets, true)) {
            $this->enabledWidgets = array_values(array_filter(
                $this->enabledWidgets,
                static fn (string $enabledWidget): bool => $enabledWidget !== $widgetKey
            ));
        } else {
            $this->enabledWidgets[] = $widgetKey;
        }

        $gridLayoutService = app(DashboardGridLayoutService::class);
        $registry = app(DashboardWidgetRegistry::class);
        $definitions = $registry->widgetsForApp('assets', auth()->user());
        $widgetMeta = $gridLayoutService->widgetMetaByKeyFromDefinitions($definitions);
        $allowedLookup = array_fill_keys(array_keys($widgetMeta), true);

        $this->enabledWidgets = $gridLayoutService->sanitizeEnabledWidgets(
            $this->enabledWidgets,
            $allowedLookup,
            [],
        );
        $this->layout = $gridLayoutService->normalizeLayout(
            $widgetMeta,
            $this->enabledWidgets,
            $this->layout,
        );
        $this->persistDashboardSettings();

        $this->dispatch('assets-dashboard-sync', layout: $this->layout);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function saveLayout(array $items): void
    {
        $validated = validator(
            ['items' => $items],
            [
                'items' => ['array'],
                'items.*.widgetKey' => ['required', 'string', Rule::in($this->enabledWidgets)],
                'items.*.x' => ['required', 'integer', 'min:0'],
                'items.*.y' => ['required', 'integer', 'min:0'],
                'items.*.w' => ['required', 'integer', 'min:1', 'max:12'],
                'items.*.h' => ['required', 'integer', 'min:1', 'max:24'],
            ]
        )->validate();

        $gridLayoutService = app(DashboardGridLayoutService::class);
        $registry = app(DashboardWidgetRegistry::class);
        $definitions = $registry->widgetsForApp('assets', auth()->user());
        $widgetMeta = $gridLayoutService->widgetMetaByKeyFromDefinitions($definitions);

        $this->layout = $gridLayoutService->normalizeLayout(
            $widgetMeta,
            $this->enabledWidgets,
            $validated['items'],
        );
        $this->persistDashboardSettings();
        $this->skipRender();
    }

    /**
     * @return array<int, array{key: string, title: string, description: string, component: string, defaultW: int, defaultH: int, minW: int, minH: int, defaultEnabled: bool, mandatory: bool}>
     */
    public function enabledWidgetDefinitions(): array
    {
        $enabledLookup = array_fill_keys($this->enabledWidgets, true);

        return array_values(array_filter(
            $this->availableWidgets,
            static fn (array $widget): bool => isset($enabledLookup[$widget['key']])
        ));
    }

    private function persistDashboardSettings(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $user->settings = $user->settings->updateAppSettings('assets', [
            'dashboard' => [
                'version' => 1,
                'enabledWidgets' => $this->enabledWidgets,
                'layout' => $this->layout,
            ],
        ]);
        $user->save();
    }

    /**
     * @return list<string>
     */
    private function availableWidgetKeys(): array
    {
        return array_values(array_map(
            static fn (array $widget): string => $widget['key'],
            $this->availableWidgets
        ));
    }
};
?>



<div>
    <x-intranet-app-assets::assets-layout
        heading="Assets Dashboard"
        subheading="Individuelle Startseite für die Assets-App"
        :render-app-index-auto="false"
    >
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <flux:text class="text-zinc-500 dark:text-white">Widgets auswählen und per Drag & Drop anordnen.</flux:text>
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" icon="squares-plus" icon-trailing="chevron-down">Widgets</flux:button>
                    <flux:menu>
                        @foreach($availableWidgets as $widget)
                            <flux:menu.item wire:click="toggleWidget('{{ $widget['key'] }}')">
                                <div class="flex w-full items-center justify-between gap-3">
                                    <span>{{ $widget['title'] }}</span>
                                    @if(in_array($widget['key'], $enabledWidgets, true))
                                        <flux:icon name="check" class="size-4 text-green-600" />
                                    @endif
                                </div>
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            </div>

            <div
                class="grid-stack"
                id="assets-dashboard-grid"
                wire:key="assets-dashboard-grid-{{ md5(json_encode($enabledWidgets)) }}"
            >
                @foreach($this->enabledWidgetDefinitions() as $widget)
                    @php
                        $layoutItem = collect($layout)->firstWhere('widgetKey', $widget['key']) ?? [
                            'x' => 0,
                            'y' => 0,
                            'w' => $widget['defaultW'],
                            'h' => $widget['defaultH'],
                        ];
                    @endphp
                    <div
                        class="grid-stack-item"
                        gs-id="{{ $widget['key'] }}"
                        gs-x="{{ $layoutItem['x'] }}"
                        gs-y="{{ $layoutItem['y'] }}"
                        gs-w="{{ $layoutItem['w'] }}"
                        gs-h="{{ $layoutItem['h'] }}"
                        gs-min-w="{{ $widget['minW'] }}"
                        gs-min-h="{{ $widget['minH'] }}"
                        wire:key="assets-dashboard-item-{{ $widget['key'] }}"
                    >
                        <div class="grid-stack-item-content h-full min-h-0 p-1">
                            <livewire:dynamic-component
                                :is="$widget['component']"
                                :key="'assets-dashboard-widget-'.$widget['key']"
                                lazy
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>

@include('intranet-app-base::dashboard.grid-stack-assets')

@script
    @include('intranet-app-base::dashboard.grid-stack-init', [
        'gridElementId' => 'assets-dashboard-grid',
        'syncEventName' => 'assets-dashboard-sync',
        'saveMethod' => 'saveLayout',
    ])
@endscript

@push('app-styles')
<style>
    #assets-dashboard-grid .grid-stack-item-content {
        background: transparent;
        overflow: hidden;
    }
</style>
@endpush@include('intranet-app-base::dashboard.grid-stack-assets')

@script
    @include('intranet-app-base::dashboard.grid-stack-init', [
        'gridElementId' => 'assets-dashboard-grid',
        'syncEventName' => 'assets-dashboard-sync',
        'saveMethod' => 'saveLayout',
    ])
@endscript

e App\Models\User;
use Hwkdo\IntranetAppBase\Services\DashboardGridLayoutService;
use Hwkdo\IntranetAppBase\Services\DashboardWidgetRegistry;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Assets Dashboard')] class extends Component
{
    /** @var array<int, array{key: string, title: string, description: string, component: string, defaultW: int, defaultH: int, minW: int, minH: int, defaultEnabled: bool, mandatory: bool}> */
    public array $availableWidgets = [];

    /** @var list<string> */
    public array $enabledWidgets = [];

    /** @var array<int, array{widgetKey: string, x: int, y: int, w: int, h: int}> */
    public array $layout = [];

    public function mount(DashboardWidgetRegistry $registry, DashboardGridLayoutService $gridLayoutService): void
    {
        /** @var User $user */
        $user = auth()->user();

        $definitions = $registry->widgetsForApp('assets', $user);
        $this->availableWidgets = array_map(static function ($definition): array {
            return [
                'key' => $definition->key,
                'title' => $definition->title,
                'description' => $definition->description,
                'component' => $definition->component,
                'defaultW' => $definition->defaultW,
                'defaultH' => $definition->defaultH,
                'minW' => $definition->minW,
                'minH' => $definition->minH,
                'defaultEnabled' => $definition->defaultEnabled,
                'mandatory' => $definition->mandatory,
            ];
        }, $definitions);

        $widgetMeta = $gridLayoutService->widgetMetaByKeyFromDefinitions($definitions);
        $allowedLookup = array_fill_keys(array_keys($widgetMeta), true);

        $settings = $user->settings->app->assets->dashboard ?? [];
        $savedEnabled = Arr::wrap($settings['enabledWidgets'] ?? []);
        $savedLayout = Arr::wrap($settings['layout'] ?? []);
        $defaultEnabled = collect($this->availableWidgets)
            ->filter(static fn (array $widget): bool => $widget['defaultEnabled'] === true)
            ->pluck('key')
            ->values()
            ->all();

        $this->enabledWidgets = $gridLayoutService->sanitizeEnabledWidgets(
            $savedEnabled !== [] ? $savedEnabled : $defaultEnabled,
            $allowedLookup,
            [],
        );
        $this->layout = $gridLayoutService->normalizeLayout(
            $widgetMeta,
            $this->enabledWidgets,
            $savedLayout,
        );
        $this->persistDashboardSettings();
    }

    public function toggleWidget(string $widgetKey): void
    {
        if (! in_array($widgetKey, $this->availableWidgetKeys(), true)) {
            return;
        }

        if (in_array($widgetKey, $this->enabledWidgets, true)) {
            $this->enabledWidgets = array_values(array_filter(
                $this->enabledWidgets,
                static fn (string $enabledWidget): bool => $enabledWidget !== $widgetKey
            ));
        } else {
            $this->enabledWidgets[] = $widgetKey;
        }

        $gridLayoutService = app(DashboardGridLayoutService::class);
        $registry = app(DashboardWidgetRegistry::class);
        $definitions = $registry->widgetsForApp('assets', auth()->user());
        $widgetMeta = $gridLayoutService->widgetMetaByKeyFromDefinitions($definitions);
        $allowedLookup = array_fill_keys(array_keys($widgetMeta), true);

        $this->enabledWidgets = $gridLayoutService->sanitizeEnabledWidgets(
            $this->enabledWidgets,
            $allowedLookup,
            [],
        );
        $this->layout = $gridLayoutService->normalizeLayout(
            $widgetMeta,
            $this->enabledWidgets,
            $this->layout,
        );
        $this->persistDashboardSettings();

        $this->dispatch('assets-dashboard-sync', layout: $this->layout);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function saveLayout(array $items): void
    {
        $validated = validator(
            ['items' => $items],
            [
                'items' => ['array'],
                'items.*.widgetKey' => ['required', 'string', Rule::in($this->enabledWidgets)],
                'items.*.x' => ['required', 'integer', 'min:0'],
                'items.*.y' => ['required', 'integer', 'min:0'],
                'items.*.w' => ['required', 'integer', 'min:1', 'max:12'],
                'items.*.h' => ['required', 'integer', 'min:1', 'max:24'],
            ]
        )->validate();

        $gridLayoutService = app(DashboardGridLayoutService::class);
        $registry = app(DashboardWidgetRegistry::class);
        $definitions = $registry->widgetsForApp('assets', auth()->user());
        $widgetMeta = $gridLayoutService->widgetMetaByKeyFromDefinitions($definitions);

        $this->layout = $gridLayoutService->normalizeLayout(
            $widgetMeta,
            $this->enabledWidgets,
            $validated['items'],
        );
        $this->persistDashboardSettings();
        $this->skipRender();
    }

    /**
     * @return array<int, array{key: string, title: string, description: string, component: string, defaultW: int, defaultH: int, minW: int, minH: int, defaultEnabled: bool, mandatory: bool}>
     */
    public function enabledWidgetDefinitions(): array
    {
        $enabledLookup = array_fill_keys($this->enabledWidgets, true);

        return array_values(array_filter(
            $this->availableWidgets,
            static fn (array $widget): bool => isset($enabledLookup[$widget['key']])
        ));
    }

    private function persistDashboardSettings(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $user->settings = $user->settings->updateAppSettings('assets', [
            'dashboard' => [
                'version' => 1,
                'enabledWidgets' => $this->enabledWidgets,
                'layout' => $this->layout,
            ],
        ]);
        $user->save();
    }

    /**
     * @return list<string>
     */
    private function availableWidgetKeys(): array
    {
        return array_values(array_map(
            static fn (array $widget): string => $widget['key'],
            $this->availableWidgets
        ));
    }
};
?>


<div>
    <x-intranet-app-assets::assets-layout
        heading="Assets Dashboard"
        subheading="Individuelle Startseite für die Assets-App"
        :render-app-index-auto="false"
    >
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <flux:text class="text-zinc-500 dark:text-white">Widgets auswählen und per Drag & Drop anordnen.</flux:text>
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" icon="squares-plus" icon-trailing="chevron-down">Widgets</flux:button>
                    <flux:menu>
                        @foreach($availableWidgets as $widget)
                            <flux:menu.item wire:click="toggleWidget('{{ $widget['key'] }}')">
                                <div class="flex w-full items-center justify-between gap-3">
                                    <span>{{ $widget['title'] }}</span>
                                    @if(in_array($widget['key'], $enabledWidgets, true))
                                        <flux:icon name="check" class="size-4 text-green-600" />
                                    @endif
                                </div>
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            </div>

            <div
                class="grid-stack"
                id="assets-dashboard-grid"
                wire:key="assets-dashboard-grid-{{ md5(json_encode($enabledWidgets)) }}"
            >
                @foreach($this->enabledWidgetDefinitions() as $widget)
                    @php
                        $layoutItem = collect($layout)->firstWhere('widgetKey', $widget['key']) ?? [
                            'x' => 0,
                            'y' => 0,
                            'w' => $widget['defaultW'],
                            'h' => $widget['defaultH'],
                        ];
                    @endphp
                    <div
                        class="grid-stack-item"
                        gs-id="{{ $widget['key'] }}"
                        gs-x="{{ $layoutItem['x'] }}"
                        gs-y="{{ $layoutItem['y'] }}"
                        gs-w="{{ $layoutItem['w'] }}"
                        gs-h="{{ $layoutItem['h'] }}"
                        gs-min-w="{{ $widget['minW'] }}"
                        gs-min-h="{{ $widget['minH'] }}"
                        wire:key="assets-dashboard-item-{{ $widget['key'] }}"
                    >
                        <div class="grid-stack-item-content h-full min-h-0 p-1">
                            <livewire:dynamic-component
                                :is="$widget['component']"
                                :key="'assets-dashboard-widget-'.$widget['key']"
                                lazy
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>

@include('intranet-app-base::dashboard.grid-stack-assets')

@script
    @include('intranet-app-base::dashboard.grid-stack-init', [
        'gridElementId' => 'assets-dashboard-grid',
        'syncEventName' => 'assets-dashboard-sync',
        'saveMethod' => 'saveLayout',
    ])
@endscript

@push('app-styles')
<style>
    #assets-dashboard-grid .grid-stack-item-content {
        background: transparent;
        overflow: hidden;
    }
</style>
@endpush
