<?php

use App\Models\User;
use Hwkdo\IntranetAppBase\Services\DashboardWidgetRegistry;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Assets Dashboard')] class extends Component
{
    /** @var array<int, array{key: string, title: string, description: string, component: string, defaultW: int, defaultH: int, minW: int, minH: int, defaultEnabled: bool}> */
    public array $availableWidgets = [];

    /** @var list<string> */
    public array $enabledWidgets = [];

    /** @var array<int, array{widgetKey: string, x: int, y: int, w: int, h: int}> */
    public array $layout = [];

    public function mount(DashboardWidgetRegistry $registry): void
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
            ];
        }, $definitions);

        $settings = $user->settings->app->assets->dashboard ?? [];
        $savedEnabled = Arr::wrap($settings['enabledWidgets'] ?? []);
        $savedLayout = Arr::wrap($settings['layout'] ?? []);
        $defaultEnabled = collect($this->availableWidgets)
            ->filter(static fn (array $widget): bool => $widget['defaultEnabled'] === true)
            ->pluck('key')
            ->values()
            ->all();

        $this->enabledWidgets = $this->sanitizeEnabledWidgets($savedEnabled !== [] ? $savedEnabled : $defaultEnabled);
        $this->layout = $this->normalizeLayout($savedLayout, $this->enabledWidgets);
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

        $this->enabledWidgets = $this->sanitizeEnabledWidgets($this->enabledWidgets);
        $this->layout = $this->normalizeLayout($this->layout, $this->enabledWidgets);
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

        $this->layout = $this->normalizeLayout($validated['items'], $this->enabledWidgets);
        $this->persistDashboardSettings();
        $this->skipRender();
    }

    /**
     * @return array<int, array{key: string, title: string, description: string, component: string, defaultW: int, defaultH: int, minW: int, minH: int, defaultEnabled: bool}>
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
     * @param  array<int, string|mixed>  $widgetKeys
     * @return list<string>
     */
    private function sanitizeEnabledWidgets(array $widgetKeys): array
    {
        $allowed = array_fill_keys($this->availableWidgetKeys(), true);
        $sanitized = [];

        foreach ($widgetKeys as $widgetKey) {
            if (! is_string($widgetKey) || ! isset($allowed[$widgetKey])) {
                continue;
            }

            if (! in_array($widgetKey, $sanitized, true)) {
                $sanitized[] = $widgetKey;
            }
        }

        return $sanitized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidateLayout
     * @param  list<string>  $enabledWidgetKeys
     * @return array<int, array{widgetKey: string, x: int, y: int, w: int, h: int}>
     */
    private function normalizeLayout(array $candidateLayout, array $enabledWidgetKeys): array
    {
        $widgetMeta = collect($this->availableWidgets)->keyBy('key');
        $enabledLookup = array_fill_keys($enabledWidgetKeys, true);
        $layoutByKey = [];

        foreach ($candidateLayout as $item) {
            $widgetKey = $item['widgetKey'] ?? null;
            if (! is_string($widgetKey) || ! isset($enabledLookup[$widgetKey]) || ! $widgetMeta->has($widgetKey)) {
                continue;
            }

            $meta = $widgetMeta->get($widgetKey);
            $w = max((int) $meta['minW'], min(12, (int) ($item['w'] ?? $meta['defaultW'])));
            $h = max((int) $meta['minH'], min(24, (int) ($item['h'] ?? $meta['defaultH'])));

            $layoutByKey[$widgetKey] = [
                'widgetKey' => $widgetKey,
                'x' => max(0, min(11, (int) ($item['x'] ?? 0))),
                'y' => max(0, (int) ($item['y'] ?? 0)),
                'w' => $w,
                'h' => $h,
            ];
        }

        $nextY = collect($layoutByKey)->max('y');
        $nextY = is_int($nextY) ? $nextY + 1 : 0;

        foreach ($enabledWidgetKeys as $widgetKey) {
            if (isset($layoutByKey[$widgetKey])) {
                continue;
            }

            $meta = $widgetMeta->get($widgetKey);
            $layoutByKey[$widgetKey] = [
                'widgetKey' => $widgetKey,
                'x' => 0,
                'y' => $nextY,
                'w' => (int) $meta['defaultW'],
                'h' => (int) $meta['defaultH'],
            ];

            $nextY += (int) $meta['defaultH'];
        }

        return array_values($layoutByKey);
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
                        <div class="grid-stack-item-content p-1">
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

@assets
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack.min.css">
    <script src="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack-all.js"></script>
@endassets

@script
<script>
    let assetsDashboardGrid = null;

    const rebuildAssetsDashboardGrid = () => {
        const element = $wire.$el.querySelector('#assets-dashboard-grid');
        if (!element || typeof GridStack === 'undefined') {
            return;
        }

        if (assetsDashboardGrid !== null) {
            assetsDashboardGrid.off('change');
            assetsDashboardGrid.destroy(false);
            assetsDashboardGrid = null;
        }

        assetsDashboardGrid = GridStack.init({
            column: 12,
            margin: 8,
            float: true,
            cellHeight: 80,
        }, element);

        assetsDashboardGrid.compact();
        assetsDashboardGrid.cellHeight(80);

        let saveTimeout = null;
        assetsDashboardGrid.on('change', () => {
            if (saveTimeout !== null) {
                window.clearTimeout(saveTimeout);
            }

            saveTimeout = window.setTimeout(() => {
                if (assetsDashboardGrid === null) {
                    return;
                }

                const layout = assetsDashboardGrid.engine.nodes.map((node) => ({
                    widgetKey: String(node.id ?? node.el?.getAttribute('gs-id') ?? ''),
                    x: Number(node.x ?? 0),
                    y: Number(node.y ?? 0),
                    w: Number(node.w ?? 1),
                    h: Number(node.h ?? 1),
                })).filter((item) => item.widgetKey !== '');

                $wire.saveLayout(layout);
            }, 250);
        });
    };

    const initAssetsDashboardGrid = () => {
        rebuildAssetsDashboardGrid();
    };

    initAssetsDashboardGrid();

    $wire.$on('assets-dashboard-sync', () => {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                rebuildAssetsDashboardGrid();
            });
        });
    });
</script>
@endscript

@push('app-styles')
<style>
    #assets-dashboard-grid .grid-stack-item-content {
        background: transparent;
        overflow: hidden;
    }
</style>
@endpush
