<?php

use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\RecipientHandoverConfirmationService;
use Hwkdo\IntranetAppAssets\Support\BulkRecipientHandoverSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Übergaben per Signopad bestätigen')] class extends Component
{
    public function mount(): void
    {
        $payload = BulkRecipientHandoverSession::getConfirmPayload();
        if ($payload === null || (int) $payload['recipient_user_id'] !== (int) auth()->id()) {
            BulkRecipientHandoverSession::forgetMyAssetsBulkSelection();
            $this->js(BulkSelectionUi::livewireClearSelectionJs());
            session()->flash('error', 'Ungültige oder abgelaufene Mehrfachaktion.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);

            return;
        }
    }

    #[Computed]
    public function handovers(): \Illuminate\Database\Eloquent\Collection
    {
        $payload = BulkRecipientHandoverSession::getConfirmPayload();
        if ($payload === null || (int) $payload['recipient_user_id'] !== (int) auth()->id()) {
            return collect();
        }

        return Handover::query()
            ->whereIn('id', $payload['handover_ids'])
            ->where('recipient_user_id', auth()->id())
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
            ->with('asset')
            ->get();
    }

    #[On('signature-confirmed')]
    public function onSignatureConfirmed(string $img_src, string $base64, array $checkboxes): void
    {
        $payload = BulkRecipientHandoverSession::getConfirmPayload();
        if ($payload === null || (int) $payload['recipient_user_id'] !== (int) auth()->id()) {
            return;
        }

        $service = app(RecipientHandoverConfirmationService::class);
        $userId = (int) auth()->id();
        $processed = 0;
        $failed = 0;

        foreach ($this->handovers as $handover) {
            try {
                $service->confirmForRecipient(
                    $handover,
                    $userId,
                    RecipientHandoverConfirmationService::METHOD_SIGNOPAD,
                    $base64,
                );
                $processed++;
            } catch (\InvalidArgumentException) {
                $failed++;
            }
        }

        BulkRecipientHandoverSession::forgetConfirmPending();
        BulkRecipientHandoverSession::forgetMyAssetsBulkSelection();
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
        session()->flash('message', "Übergaben per Signopad bestätigt: {$processed} erfolgreich".($failed > 0 ? ", {$failed} übersprungen." : '.'));
        $this->redirect(route('apps.assets.meine-assets'), navigate: true);
    }
}; ?>
<div>
    <x-intranet-app-assets::assets-layout
        heading="Unterschrift mit Signopad"
        subheading="{{ $this->handovers->count() }} Übergabe(n)"
    >
        <flux:card class="mb-4">
            <flux:heading size="sm" class="mb-2">Ausgewählte Übergaben</flux:heading>
            <ul class="list-inside list-disc text-sm text-zinc-600 dark:text-zinc-300">
                @foreach($this->handovers as $h)
                    <li>{{ $h->asset?->display_name ?? 'Übergabe #'.$h->id }}</li>
                @endforeach
            </ul>
        </flux:card>
        <flux:card>
            <flux:heading size="sm" class="mb-4">Bitte unterschreiben Sie für alle Übergaben</flux:heading>
            <livewire:signopad.signpad
                :fields="[]"
                textOben="Übergaben bestätigen"
                textUnten="Bitte hier unterschreiben"
            />
        </flux:card>
        <flux:button href="{{ route('apps.assets.handover.bulk.confirm') }}" variant="ghost" icon="arrow-left" class="mt-4">
            Zurück
        </flux:button>
    </x-intranet-app-assets::assets-layout>
</div>
