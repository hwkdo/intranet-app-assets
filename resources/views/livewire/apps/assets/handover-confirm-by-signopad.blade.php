<?php

use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\RecipientHandoverConfirmationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Übergabe per Signopad bestätigen')] class extends Component
{
    public Handover $handover;

    public function mount(Handover $handover): void
    {
        $this->handover = $handover->load('asset');

        if ($handover->recipient_user_id !== auth()->id()) {
            abort(403);
        }
        if ($handover->isConfirmed()) {
            session()->flash('message', 'Diese Übergabe wurde bereits bestätigt.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);
        }
        if ($handover->isRejected()) {
            session()->flash('message', 'Diese Übergabe wurde abgelehnt.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);
        }
    }

    #[On('signature-confirmed')]
    public function onSignatureConfirmed(string $img_src, string $base64, array $checkboxes): void
    {
        if ($this->handover->recipient_user_id !== auth()->id() || $this->handover->isConfirmed() || $this->handover->isRejected()) {
            return;
        }

        try {
            app(RecipientHandoverConfirmationService::class)->confirmForRecipient(
                $this->handover,
                (int) auth()->id(),
                RecipientHandoverConfirmationService::METHOD_SIGNOPAD,
                $base64,
            );
        } catch (\InvalidArgumentException) {
            return;
        }

        session()->flash('message', 'Übergabe wurde per Signopad bestätigt.');
        $this->redirect(route('apps.assets.meine-assets'), navigate: true);
    }
}; ?>
<div>
    <x-intranet-app-assets::assets-layout heading="Unterschrift mit Signopad" subheading="{{ $handover->asset?->display_name ?? 'Asset' }}">
        <flux:card>
            <flux:heading size="sm" class="mb-4">Bitte unterschreiben Sie die Übergabe</flux:heading>
            <livewire:signopad.signpad
                :fields="[]"
                textOben="Übergabe bestätigen"
                textUnten="Bitte hier unterschreiben"
            />
        </flux:card>
        <flux:button href="{{ route('apps.assets.handover.confirm', $handover) }}" variant="ghost" icon="arrow-left">
            Zurück
        </flux:button>
    </x-intranet-app-assets::assets-layout>
</div>
