<?php

use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\RecipientHandoverConfirmationService;
use Hwkdo\IntranetAppAssets\Support\BulkRecipientHandoverSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component
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

        $service = app(RecipientHandoverConfirmationService::class);
        $userId = (int) auth()->id();
        $processed = 0;
        $failed = 0;

        $handovers = Handover::query()
            ->whereIn('id', $payload['handover_ids'])
            ->where('recipient_user_id', $userId)
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
            ->get();

        foreach ($handovers as $handover) {
            try {
                $service->confirmForRecipient(
                    $handover,
                    $userId,
                    RecipientHandoverConfirmationService::METHOD_PASSWORD,
                    null,
                );
                $processed++;
            } catch (\InvalidArgumentException) {
                $failed++;
            }
        }

        BulkRecipientHandoverSession::forgetConfirmPending();
        BulkRecipientHandoverSession::forgetMyAssetsBulkSelection();
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
        session()->flash('message', "Übergaben per Passwort bestätigt: {$processed} erfolgreich".($failed > 0 ? ", {$failed} übersprungen." : '.'));
        $this->redirect(route('apps.assets.meine-assets'), navigate: true);
    }
}; ?>
<div>
    <div class="flex items-center justify-center py-12">
        <svg class="h-8 w-8 animate-spin text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>
</div>
