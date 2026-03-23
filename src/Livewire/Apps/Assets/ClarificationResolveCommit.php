<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Services\AssetClarificationAdminResolutionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Klärfall speichern')]
class ClarificationResolveCommit extends Component
{
    public Asset $asset;

    public function mount(Asset $asset): void
    {
        $this->authorize('manage-app-assets');

        $this->asset = $asset;

        $key = AssetClarificationAdminResolutionService::PENDING_RESOLUTION_SESSION_KEY;
        $pending = session()->get($key);

        if (! is_array($pending)
            || (int) ($pending['asset_id'] ?? 0) !== $asset->id
            || (int) ($pending['admin_user_id'] ?? 0) !== (int) auth()->id()) {
            session()->forget($key);
            session()->flash('error', 'Ungültige oder abgelaufene Aktion. Bitte das Formular erneut ausfüllen.');

            $this->redirect(route('apps.assets.admin.clarification.resolve', $asset), navigate: true);

            return;
        }

        if (! $asset->is_clarification) {
            session()->forget($key);
            session()->flash('message', 'Dieses Asset ist nicht mehr in Klärung.');

            $this->redirect(route('apps.assets.admin.clarifications'), navigate: true);

            return;
        }

        session()->forget($key);

        $service = app(AssetClarificationAdminResolutionService::class);

        try {
            $service->resolve(
                $asset,
                (int) auth()->id(),
                (string) $pending['resolution'],
                isset($pending['new_owner_user_id']) ? (int) $pending['new_owner_user_id'] : null,
                isset($pending['location']) ? (string) $pending['location'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            $this->redirect(route('apps.assets.admin.clarification.resolve', $asset), navigate: true);

            return;
        }

        session()->flash('message', 'Der Klärfall wurde bearbeitet und das Asset aktualisiert.');

        $this->redirect(route('apps.assets.admin.clarifications'), navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.clarification-resolve-commit');
    }
}
