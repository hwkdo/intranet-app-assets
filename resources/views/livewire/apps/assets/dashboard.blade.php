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
    private const WIDGET_ITEM_COUNT_MIN = 1;

    private const WIDGET_ITEM_COUNT_MAX = 30;

    /** @var array<int, array{key: string, title: string, description: string, component: string, defaultW: int, defaultH: int, minW: int, minH: int, defaultEnabled: bool}> */
    public array $availableWidgets = [];

    /** @var list<string> */
    public array $enabledWidgets = [];

    /** @var array<int, array{widgetKey: string, x: int, y: int, w: int, h: int}> */
    public array $layout = [];

    /** @var array<string, int> */
    public array $widgetItemCounts = [];

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
        $savedItemCounts = Arr::wrap($settings['widgetItemCounts'] ?? []);
        $defaultEnabled = collect($this->availableWidgets)
            ->filter(static fn (array $widget): bool => $widget['defaultEnabled'] === true)
            ->pluck('key')
            ->values()
            ->all();

        $this->enabledWidgets = $this->sanitizeEnabledWidgets($savedEnabled !== [] ? $savedEnabled : $defaultEnabled);
        $this->layout = $this->normalizeLayout($savedLayout, $this->enabledWidgets);
        $this->widgetItemCounts = $this->sanitizeWidgetItemCounts($savedItemCounts);
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

    public function saveWidgetItemCount(string $widgetKey, mixed $value): void
    {
        if (! $this->supportsWidgetItemCount($widgetKey)) {
            return;
        }

        $this->widgetItemCounts[$widgetKey] = is_numeric($value) ? (int) $value : $value;

        $validated = validator(
            ['value' => $this->widgetItemCounts[$widgetKey] ?? null],
            ['value' => ['required', 'integer', 'min:'.self::WIDGET_ITEM_COUNT_MIN, 'max:'.self::WIDGET_ITEM_COUNT_MAX]],
        )->validate();

        $this->widgetItemCounts[$widgetKey] = (int) $validated['value'];
        $this->persistDashboardSettings();
        $this->skipRender();
    }

    public function resetToDefault(): void
    {
        $defaultEnabled = collect($this->availableWidgets)
            ->filter(static fn (array $widget): bool => $widget['defaultEnabled'] === true)
            ->pluck('key')
            ->values()
            ->all();

        $this->enabledWidgets = $this->sanitizeEnabledWidgets($defaultEnabled);
        $this->layout = $this->normalizeLayout([], $this->enabledWidgets);
        $this->widgetItemCounts = $this->defaultWidgetItemCounts();

        $this->persistDashboardSettings();
        $this->dispatch('assets-dashboard-sync', layout: $this->layout);
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

    public function supportsWidgetItemCount(string $widgetKey): bool
    {
        return in_array($widgetKey, $this->availableWidgetKeys(), true);
    }

    public function widgetItemCountValue(string $widgetKey): int
    {
        $value = $this->widgetItemCounts[$widgetKey] ?? 5;

        return min(max((int) $value, self::WIDGET_ITEM_COUNT_MIN), self::WIDGET_ITEM_COUNT_MAX);
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
                'widgetItemCounts' => $this->widgetItemCounts,
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

    /**
     * @param  array<mixed>  $rawCounts
     * @return array<string, int>
     */
    private function sanitizeWidgetItemCounts(array $rawCounts): array
    {
        $counts = [];

        foreach ($this->availableWidgetKeys() as $widgetKey) {
            $rawValue = $rawCounts[$widgetKey] ?? 5;
            $value = is_numeric($rawValue) ? (int) $rawValue : 5;
            $counts[$widgetKey] = min(max($value, self::WIDGET_ITEM_COUNT_MIN), self::WIDGET_ITEM_COUNT_MAX);
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function defaultWidgetItemCounts(): array
    {
        return $this->sanitizeWidgetItemCounts([]);
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
                <flux:modal.trigger name="assets-dashboard-widgets-flyout">
                    <flux:button variant="ghost" icon="squares-plus" icon-trailing="chevron-down">Widgets</flux:button>
                </flux:modal.trigger>
            </div>

            <flux:modal name="assets-dashboard-widgets-flyout" variant="flyout" class="md:max-w-lg">
                <div class="space-y-5">
                    <div class="space-y-1">
                        <flux:heading size="lg">Widgets</flux:heading>
                        <flux:text class="text-zinc-500">
                            Widgets aktivieren/deaktivieren und das Dashboard auf den Standard zurücksetzen.
                        </flux:text>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <flux:heading size="sm">Assets</flux:heading>
                            <div class="space-y-1">
                                @foreach($availableWidgets as $widget)
                                    <div class="flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                                        <button
                                            type="button"
                                            class="min-w-0 flex-1 text-left hover:opacity-90"
                                            wire:click="toggleWidget('{{ $widget['key'] }}')"
                                        >
                                            <span class="block text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $widget['title'] }}</span>
                                            @if(! empty($widget['description']))
                                                <span class="mt-0.5 block text-xs text-zinc-500 dark:text-white">{{ $widget['description'] }}</span>
                                            @endif
                                        </button>

                                        <span class="shrink-0 flex items-center gap-2">
                                            @if($this->supportsWidgetItemCount($widget['key']))
                                                <span class="w-24">
                                                    <flux:input
                                                        type="number"
                                                        min="1"
                                                        max="30"
                                                        size="sm"
                                                        :value="$this->widgetItemCountValue($widget['key'])"
                                                        wire:change="saveWidgetItemCount('{{ $widget['key'] }}', $event.target.value)"
                                                    />
                                                </span>
                                            @endif
                                            @if(in_array($widget['key'], $enabledWidgets, true))
                                                <flux:icon name="check-circle" class="size-5 text-green-600" />
                                            @else
                                                <flux:icon name="minus-circle" class="size-5 text-zinc-400" />
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-between gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:button
                            variant="danger"
                            icon="arrow-path"
                            wire:click="resetToDefault"
                            wire:confirm="Dashboard wirklich auf Standard zurücksetzen?"
                        >
                            Zurücksetzen auf Standard
                        </flux:button>
                        <flux:modal.close>
                            <flux:button variant="ghost">Schließen</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            </flux:modal>

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

    @include('intranet-app-base::dashboard.grid-stack-assets')

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
</div>

@push('app-styles')
    <style>
        #assets-dashboard-grid .grid-stack-item-content {
            background: transparent;
            overflow: hidden;
        }
    </style>
@endpush
