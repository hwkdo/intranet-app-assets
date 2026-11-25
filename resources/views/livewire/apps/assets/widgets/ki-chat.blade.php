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
        return (string) config('openwebui-api-laravel.base_api_url', 'https://chat.ai.hwk-do.com/api');
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
        <div class="min-h-0 flex-1">
            @livewire('prism-chat', [
                'appIdentifier' => 'assets',
                'model' => $this->model,
                'apiKey' => $this->apiKey,
                'baseUrl' => $this->baseUrl,
                'useMcpTools' => true,
                'embedded' => true,
                'embeddedTitle' => 'Assets Chat',
                'fullscreenUrl' => route('apps.assets.chat'),
                'suggestions' => [
                    'Welche Assets sind mir aktuell zugeordnet?',
                    'Suche alle Notebooks von Hersteller Lenovo.',
                    'Prüfe, ob die Bestellnummer gültig ist und lege daraus ein Asset an.',
                    'Analysiere die Rechnung zu einem Asset.',
                ],
            ], key('assets-dashboard-ki-chat'))
        </div>
    @else
        <section class="glass-card flex h-full min-h-0 flex-col overflow-hidden p-0">
            <x-intranet-app-base::dashboard.chat-widget-header title="Assets Chat" :href="route('apps.assets.chat')" />
            <div class="flex flex-1 items-center justify-center">
                <x-intranet-app-assets::hwk-empty bare icon="key" title="API-Token fehlt">
                    Ohne Token ist der KI-Chat nicht nutzbar.
                    <div class="mt-4">
                        <flux:button variant="primary" href="{{ route('settings.all') }}">
                            Zu den Einstellungen
                        </flux:button>
                    </div>
                </x-intranet-app-assets::hwk-empty>
            </div>
        </section>
    @endif
</div>
