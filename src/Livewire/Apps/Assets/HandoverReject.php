<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Übergabe ablehnen')]
class HandoverReject extends Component
{
    public Handover $handover;

    #[Validate('required|string|min:3|max:5000')]
    public string $rejectionReason = '';

    public function mount(Handover $handover): void
    {
        $this->handover = $handover->load('asset.type', 'asset.vendor', 'recipient', 'issuer');

        if ($handover->isLoan()) {
            abort(403, 'Verleih-Übergaben können nicht abgelehnt werden.');
        }

        if ($handover->recipient_user_id !== auth()->id()) {
            abort(403);
        }
        if ($handover->isConfirmed()) {
            session()->flash('message', 'Diese Übergabe wurde bereits bestätigt.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);
        }
        if ($handover->isRejected()) {
            session()->flash('message', 'Diese Übergabe wurde bereits abgelehnt.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);
        }
    }

    public function reject(): void
    {
        $this->validate();

        $asset = $this->handover->asset;
        if ($asset === null) {
            session()->flash('error', 'Asset nicht gefunden.');

            return;
        }

        $reason = trim($this->rejectionReason);
        $userId = auth()->id();

        $this->handover->notes()->create([
            'note' => 'Übergabe abgelehnt — Begründung des Empfängers:'."\n\n".$reason,
            'user_id' => $userId,
        ]);

        $this->handover->update([
            'rejected_at' => now(),
            'rejected_by_user_id' => $userId,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventHandoverRejectedByRecipient,
            'user_id' => $userId,
            'reason' => $reason,
            'meta' => [
                'handover_id' => $this->handover->id,
                'recipient_user_id' => $this->handover->recipient_user_id,
            ],
        ]);

        session()->flash('message', 'Die Übergabe wurde abgelehnt. Die IT wird informiert und kann den Vorgang bearbeiten.');
        $this->redirect(route('apps.assets.meine-assets'), navigate: true);
    }

    public function render(): View
    {
        return view('intranet-app-assets::livewire.apps.assets.handover-reject');
    }
}
