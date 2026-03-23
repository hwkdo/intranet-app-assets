<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Rückgabe einleiten')]
class HandoverReturnInitiate extends Component
{
    public Handover $handover;

    #[Validate('nullable|string|max:5000')]
    public string $note = '';

    public bool $initiatedByAdmin = false;

    public function mount(Handover $handover): void
    {
        $this->handover = $handover->load(['asset.type', 'asset.vendor', 'recipient', 'issuer']);
        $isAdmin = auth()->user()?->can('manage-app-assets') ?? false;
        $this->initiatedByAdmin = $isAdmin;

        if (! $isAdmin && $handover->recipient_user_id !== auth()->id()) {
            abort(403);
        }

        if (! $handover->isConfirmed() || $handover->isRejected()) {
            session()->flash('message', 'Rückgabe ist nur bei bestätigter Übergabe möglich.');
            $this->redirect(route('apps.assets.handover.show', $handover), navigate: true);
        }

        $asset = $handover->asset;
        if ($asset === null || (! $isAdmin && (int) $asset->user_id !== (int) auth()->id())) {
            session()->flash('message', 'Sie sind laut System nicht der aktuelle Besitzer dieses Assets.');
            $this->redirect(route('apps.assets.handover.show', $handover), navigate: true);
        }

        if ($handover->assetReturns()->whereNull('completed_at')->exists()) {
            session()->flash('message', 'Für diese Übergabe wurde bereits eine Rückgabe eingeleitet.');
            $this->redirect(route('apps.assets.handover.show', $handover), navigate: true);
        }

        if ($handover->assetReturns()->whereNotNull('completed_at')->exists()) {
            session()->flash('message', 'Diese Übergabe ist bereits zurückgegeben worden.');
            $this->redirect(route('apps.assets.handover.show', $handover), navigate: true);
        }
    }

    public function submit(): void
    {
        $this->validate();

        $userId = auth()->id();
        $noteText = trim($this->note);

        $return = AssetReturn::create([
            'handover_id' => $this->handover->id,
            'initiated_by_user_id' => $userId,
        ]);

        if ($noteText !== '') {
            $return->notes()->create([
                'note' => 'Rückgabe eingeleitet:'."\n\n".$noteText,
                'user_id' => $userId,
            ]);
        }

        $asset = $this->handover->asset;
        if ($asset !== null) {
            $asset->historyEntries()->create([
                'event' => AssetHistory::EventReturnInitiatedByHolder,
                'user_id' => $userId,
                'reason' => $noteText !== '' ? $noteText : 'Rückgabe eingeleitet.',
                'meta' => [
                    'asset_return_id' => $return->id,
                    'handover_id' => $this->handover->id,
                    'initiated_by_admin' => $this->initiatedByAdmin,
                ],
            ]);
        }

        session()->flash('message', 'Die Rückgabe wurde eingeleitet. Die IT bearbeitet den Vorgang und bestätigt den physischen Empfang.');
        $this->redirect(route('apps.assets.handover.show', $this->handover), navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.handover-return-initiate');
    }
}
