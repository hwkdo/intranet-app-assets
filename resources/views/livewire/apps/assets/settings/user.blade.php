<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Assets - Meine Einstellungen')] class extends Component {
    public function render(): string
    {
        return <<<'HTML'
        <x-intranet-app-assets::assets-layout heading="Meine Einstellungen" subheading="Persönliche Einstellungen für die Assets App">
            @livewire('intranet-app-base::user-settings', ['appIdentifier' => 'assets'])
        </x-intranet-app-assets::assets-layout>
        HTML;
    }
};
