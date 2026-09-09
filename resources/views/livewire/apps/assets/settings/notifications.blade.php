<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Assets – Benachrichtigungen')] class extends Component {
    public function render(): string
    {
        return <<<'HTML'
        <div>
            <x-intranet-app-assets::assets-layout heading="Benachrichtigungen" subheading="Benachrichtigungseinstellungen für Assets">
                @livewire('intranet-app-base::notification-settings', ['appIdentifier' => 'assets'])
            </x-intranet-app-assets::assets-layout>
        </div>
        HTML;
    }
}; ?>
