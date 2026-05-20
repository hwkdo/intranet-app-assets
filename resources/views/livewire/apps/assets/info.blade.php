<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Assets - App-Info')] class extends Component {
    public function render(): string
    {
        return <<<'HTML'
        <x-intranet-app-assets::assets-layout heading="App-Info" subheading="Installierte Version und Release-Historie">
            @livewire('intranet-app-base::app-info', ['appIdentifier' => 'assets'])
        </x-intranet-app-assets::assets-layout>
        HTML;
    }
}; ?>
