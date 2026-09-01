<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Services\AssetMissingAdminResolutionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Vermisst-Fall speichern')]
class MissingResolveCommit extends Component
{
    public Asset $asset;

    public function mount(Asset $asset): void
    {
        $this->authorize('manage-app-assets');

        $this->asset = $asset;

        $key = AssetMissingAdminResolutionService::PENDING_RESOLUTION_SESSION_KEY;
        $pending = session()->get($key);

        if (! is_array($pending)
            || (int) ($pending['asset_id'] ?? 0) !== $asset->id
            || (int) ($pending['admin_user_id'] ?? 0) !== (int) auth()->id()) {
            session()->forget($key);
            session()->flash('error', 'Ungültige oder abgelaufene Aktion. Bitte das Formular erneut ausfüllen.');

            $this->redirect(route('apps.assets.admin.missing.resolve', $asset), navigate: true);

            return;
        }

        if (! $asset->is_missing) {
            session()->forget($key);
            session()->flash('message', 'Dieses Asset ist nicht mehr als vermisst markiert.');

            $this->redirect(route('apps.assets.admin.missing'), navigate: true);

            return;
        }

        session()->forget($key);

        $service = app(AssetMissingAdminResolutionService::class);

        try {
            $service->resolve(
                $asset,
                (int) auth()->id(),
                (string) $pending['resolution'],
                isset($pending['new_owner_user_id']) ? (int) $pending['new_owner_user_id'] : null,
                isset($pending['location']) ? (string) $pending['location'] : null,
                isset($pending['note']) ? (string) $pending['note'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            $this->redirect(route('apps.assets.admin.missing.resolve', $asset), navigate: true);

            return;
        }

        session()->flash('message', 'Der Vermisst-Fall wurde bearbeitet und das Asset aktualisiert.');

        $this->redirect(route('apps.assets.admin.missing'), navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.missing-resolve-commit');
    }
}
