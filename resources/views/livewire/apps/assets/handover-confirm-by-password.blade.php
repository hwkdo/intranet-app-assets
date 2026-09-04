<?php

use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\AssetLoanService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public function mount(Handover $handover): void
    {
        if ($handover->recipient_user_id !== auth()->id()) {
            abort(403);
        }
        if ($handover->isConfirmed()) {
            session()->flash('message', 'Diese Übergabe wurde bereits bestätigt.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);
            return;
        }
        if ($handover->isRejected()) {
            session()->flash('message', 'Diese Übergabe wurde abgelehnt.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);
            return;
        }

        $handover->update([
            'confirmed_at' => now(),
            'confirmation_method' => 'password',
            'pending_confirmation_channel' => null,
        ]);
        $asset = $handover->asset;
        if ($asset !== null) {
            $clearedFlags = [];
            if ($asset->is_clarification) {
                $clearedFlags[] = 'is_clarification';
            }
            if ($asset->is_missing) {
                $clearedFlags[] = 'is_missing';
            }

            $asset->update([
                'is_clarification' => false,
                'is_missing' => false,
            ]);

            if ($clearedFlags !== []) {
                $asset->historyEntries()->create([
                    'event' => AssetHistory::EventHandoverConfirmedStatusCleared,
                    'user_id' => auth()->id(),
                    'reason' => 'Bei Bestätigung der Übergabe wurden Status-Flags zurückgesetzt.',
                    'meta' => [
                        'handover_id' => $handover->id,
                        'confirmation_method' => 'password',
                        'cleared_flags' => $clearedFlags,
                    ],
                ]);
            }
        }

        app(AssetLoanService::class)->ensureScheduledReturnAfterConfirm($handover->fresh() ?? $handover);

        session()->flash('message', 'Übergabe wurde per Passwort bestätigt.');
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
