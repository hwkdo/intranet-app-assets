<?php

use App\Data\UserSettings;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
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

<div class="flex h-full min-h-0 flex-col">
    @if ($this->hasApiKey)
        <div class="mb-2 flex shrink-0 items-center justify-between gap-2">
            <flux:heading size="sm">KI-Chat</flux:heading>
            <flux:button variant="ghost" size="sm" :href="route('apps.assets.chat')" wire:navigate>
                Vollansicht
            </flux:button>
        </div>
        <div class="min-h-0 flex-1">
            @livewire('prism-chat', [
                'appIdentifier' => 'assets',
                'model' => $this->model,
                'apiKey' => $this->apiKey,
                'baseUrl' => $this->baseUrl,
                'useMcpTools' => true,
                'embedded' => true,
            ], key('assets-dashboard-ki-chat'))
        </div>
    @else
        <x-intranet-app-base::dashboard.widget-card
            title="KI-Chat"
            description="OpenWebUI API-Token in den globalen Einstellungen hinterlegen."
        >
            <flux:callout variant="warning">
                <flux:heading size="sm">API-Token fehlt</flux:heading>
                <flux:text>
                    Ohne Token ist der KI-Chat nicht nutzbar.
                </flux:text>
            </flux:callout>
            <flux:button variant="primary" class="mt-2" href="{{ route('settings.all') }}">
                Zu den Einstellungen
            </flux:button>
        </x-intranet-app-base::dashboard.widget-card>
    @endif
</div>
