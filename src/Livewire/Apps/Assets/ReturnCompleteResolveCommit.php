<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Services\AssetReturnAdminCompletionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Rückgabe speichern')]
class ReturnCompleteResolveCommit extends Component
{
    public AssetReturn $assetReturn;

    public function mount(AssetReturn $assetReturn): void
    {
        $this->authorize('manage-app-assets');

        $this->assetReturn = $assetReturn;

        $key = AssetReturnAdminCompletionService::PENDING_SESSION_KEY;
        $pending = session()->get($key);

        if (! is_array($pending)
            || (int) ($pending['asset_return_id'] ?? 0) !== $assetReturn->id
            || (int) ($pending['admin_user_id'] ?? 0) !== (int) auth()->id()) {
            session()->forget($key);
            session()->flash('error', 'Ungültige oder abgelaufene Aktion. Bitte das Formular erneut ausfüllen.');

            $this->redirect(route('apps.assets.admin.return.complete', $assetReturn), navigate: true);

            return;
        }

        if ($assetReturn->isCompleted()) {
            session()->forget($key);
            session()->flash('message', 'Diese Rückgabe ist bereits abgeschlossen.');

            $this->redirect(route('apps.assets.admin.returns.pending'), navigate: true);

            return;
        }

        session()->forget($key);

        $service = app(AssetReturnAdminCompletionService::class);

        try {
            $service->complete(
                $assetReturn,
                (int) auth()->id(),
                (string) $pending['resolution'],
                isset($pending['new_owner_user_id']) ? (int) $pending['new_owner_user_id'] : null,
                isset($pending['location']) ? (string) $pending['location'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            $this->redirect(route('apps.assets.admin.return.complete', $assetReturn), navigate: true);

            return;
        }

        session()->flash('message', 'Rückgabe wurde abgeschlossen (Empfang bestätigt und Asset aktualisiert).');

        $this->redirect(route('apps.assets.admin.returns.pending'), navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.return-complete-resolve-commit');
    }
}
