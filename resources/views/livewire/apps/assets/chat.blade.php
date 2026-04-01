<?php

use App\Data\UserSettings;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Assets - KI-Chat')] class extends Component
{
    #[Computed]
    public function appSettings()
    {
        return IntranetAppAssetsSettings::current()?->settings;
    }

    #[Computed]
    public function apiKey(): string
    {
        $user = Auth::user();

        if (! $user) {
            return '';
        }

        $settings = UserSettings::from($user->settings);

        return (string) ($settings->ai->openWebUiApiToken ?? '');
    }

    #[Computed]
    public function model(): string
    {
        return (string) ($this->appSettings?->openWebUiModel ?? 'intranet-app-assets');
    }

    #[Computed]
    public function baseUrl(): string
    {
        return (string) config('openwebui-api-laravel.base_api_url_ollama', 'https://chat.ai.hwk-do.com/api');
    }

    #[Computed]
    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }
};
?>

<x-intranet-app-assets::assets-layout heading="KI-Chat" subheading="KI-Chat für Assets mit MCP-Server-Unterstützung">
    @if ($this->hasApiKey)
        @livewire('prism-chat', [
            'appIdentifier' => 'assets',
            'model' => $this->model,
            'apiKey' => $this->apiKey,
            'baseUrl' => $this->baseUrl,
            'useMcpTools' => true,
        ])
    @else
        <flux:card class="glass-card">
            <flux:callout variant="warning" class="mb-4">
                <flux:heading size="sm">API-Token fehlt</flux:heading>
                <flux:text>
                    Um den KI-Chat zu nutzen, müssen Sie einen OpenWebUI API-Token in Ihren globalen Einstellungen konfigurieren.
                </flux:text>
            </flux:callout>

            <flux:button
                variant="primary"
                href="{{ route('settings.all') }}"
            >
                Zu den Einstellungen
            </flux:button>
        </flux:card>
    @endif
</x-intranet-app-assets::assets-layout>
