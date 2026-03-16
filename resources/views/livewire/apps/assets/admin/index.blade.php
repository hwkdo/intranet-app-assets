<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Assets - Admin')] class extends Component {
    public string $activeTab = 'typen';
};
?>
<div>
<x-intranet-app-assets::assets-layout heading="Assets App" subheading="Admin">
    <flux:tab.group>
        <flux:tabs wire:model="activeTab">
            <flux:tab name="typen" icon="tag">Typen</flux:tab>
            <flux:tab name="hersteller" icon="building-office-2">Hersteller</flux:tab>
            <flux:tab name="seventhings" icon="arrows-right-left">Seventhings Mapping</flux:tab>
            <flux:tab name="hintergrundbild" icon="photo">Hintergrundbild</flux:tab>
            <flux:tab name="einstellungen" icon="cog-6-tooth">Einstellungen</flux:tab>
            <flux:tab name="statistiken" icon="chart-bar">Statistiken</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="typen">
            @livewire('intranet-app-assets::apps.assets.admin.asset-types')
        </flux:tab.panel>

        <flux:tab.panel name="hersteller">
            @livewire('intranet-app-assets::apps.assets.admin.asset-vendors')
        </flux:tab.panel>

        <flux:tab.panel name="seventhings">
            <div style="min-height: 400px;">
                @livewire('intranet-app-assets::apps.assets.admin.seventhings-mapping')
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="hintergrundbild">
            <div style="min-height: 400px;">
                @livewire('intranet-app-base::app-background-image', ['appIdentifier' => 'assets'])
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="einstellungen">
            <div style="min-height: 400px;">
                @livewire('intranet-app-base::admin-settings', [
                    'appIdentifier' => 'assets',
                    'settingsModelClass' => '\Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings',
                    'appSettingsClass' => '\Hwkdo\IntranetAppAssets\Data\AppSettings',
                ])
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="statistiken">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:card>
                    <flux:heading>Assets gesamt</flux:heading>
                    <div class="mt-2 text-3xl font-bold">
                        {{ \Hwkdo\IntranetAppAssets\Models\Asset::count() }}
                    </div>
                </flux:card>
                <flux:card>
                    <flux:heading>Übergaben gesamt</flux:heading>
                    <div class="mt-2 text-3xl font-bold">
                        {{ \Hwkdo\IntranetAppAssets\Models\Handover::count() }}
                    </div>
                </flux:card>
                <flux:card>
                    <flux:heading>Vermisste Assets</flux:heading>
                    <div class="mt-2 text-3xl font-bold text-red-600">
                        {{ \Hwkdo\IntranetAppAssets\Models\Asset::where('is_missing', true)->count() }}
                    </div>
                </flux:card>
            </div>
        </flux:tab.panel>
    </flux:tab.group>
</x-intranet-app-assets::assets-layout>
</div>