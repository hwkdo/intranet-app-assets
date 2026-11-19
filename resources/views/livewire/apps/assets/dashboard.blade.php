<?php

use Hwkdo\IntranetAppBase\Livewire\Concerns\InteractsWithAppDashboard;
use Hwkdo\IntranetAppBase\Services\DashboardWidgetRegistry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Assets Dashboard')] class extends Component
{
    use InteractsWithAppDashboard;

    public function mount(DashboardWidgetRegistry $registry): void
    {
        $this->initializeAppDashboard($this->dashboardAppIdentifier(), $registry);
    }

    protected function dashboardAppIdentifier(): string
    {
        return 'assets';
    }

    protected function dashboardSyncEventName(): string
    {
        return 'assets-dashboard-sync';
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
            <div class="glass-card sticky top-3 z-20 flex items-center justify-between gap-3 p-3" style="--glass-opacity: 0.92">
                <flux:text class="flex-1 text-slate-600 dark:text-white/70">Widgets auswählen und per Drag &amp; Drop anordnen.</flux:text>
                <div class="ml-auto shrink-0">
                    @include('intranet-app-base::dashboard.widgets-flyout', [
                        'modalName' => 'assets-dashboard-widgets-flyout',
                        'sections' => [
                            ['label' => 'Assets', 'widgets' => $availableWidgets],
                        ],
                        'enabledWidgets' => $enabledWidgets,
                    ])
                </div>
            </div>

            @include('intranet-app-base::dashboard.grid-container', [
                'gridElementId' => 'assets-dashboard-grid',
                'gridWireKeyPrefix' => 'assets-dashboard-grid',
                'itemWireKeyPrefix' => 'assets-dashboard-item',
                'widgetKeyPrefix' => 'assets-dashboard-widget',
                'enabledWidgets' => $enabledWidgets,
                'layout' => $layout,
                'widgets' => $this->enabledWidgetDefinitions(),
                'widgetRenderVersion' => $widgetRenderVersion,
            ])
        </div>
    </x-intranet-app-assets::assets-layout>

    @include('intranet-app-base::dashboard.grid-stack-assets')

    @script
        @include('intranet-app-base::dashboard.grid-stack-init', [
            'gridElementId' => 'assets-dashboard-grid',
            'syncEventName' => 'assets-dashboard-sync',
            'saveMethod' => 'saveLayout',
        ])
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
