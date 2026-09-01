<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Klärung anfordern')]
class AssetRequestClarification extends Component
{
    public Asset $asset;

    #[Validate('required|string|min:3|max:5000')]
    public string $reason = '';

    public function mount(Asset $asset): void
    {
        $this->asset = $asset->load(['type', 'vendor', 'owner']);

        if ($asset->trashed()) {
            abort(404);
        }

        if ((int) $asset->user_id !== (int) auth()->id()) {
            abort(403);
        }

        if ($asset->is_clarification) {
            session()->flash('message', 'Für dieses Asset läuft bereits eine Klärung.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);

            return;
        }

        $hasOpenHandover = Handover::query()
            ->where('asset_id', $asset->id)
            ->where('recipient_user_id', auth()->id())
            ->open()
            ->exists();

        if ($hasOpenHandover) {
            session()->flash(
                'error',
                'Solange Ihre Übergabe noch offen ist, nutzen Sie bitte „Übergabe ablehnen“, um eine Klärung auszulösen. „Klärung anfordern“ steht nach Bestätigung der Übergabe zur Verfügung.'
            );
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);

            return;
        }
    }

    public function submit(): void
    {
        $this->validate();

        $reason = trim($this->reason);
        $userId = auth()->id();

        $this->asset->notes()->create([
            'note' => 'Klärung angefordert — Begründung des Besitzers:'."\n\n".$reason,
            'user_id' => $userId,
        ]);

        AssetAuditContext::runWith('assets.clarification.request', function (): void {
            $this->asset->update([
                'is_clarification' => true,
            ]);
        });

        $this->asset->historyEntries()->create([
            'event' => AssetHistory::EventOwnerRequestedClarification,
            'user_id' => $userId,
            'reason' => $reason,
            'meta' => [
                'asset_id' => $this->asset->id,
            ],
        ]);

        session()->flash('message', 'Ihre Klärungsanfrage wurde übermittelt. Die IT wird den Vorgang bearbeiten.');
        $this->redirect(route('apps.assets.meine-assets'), navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.asset-request-clarification');
    }
}
