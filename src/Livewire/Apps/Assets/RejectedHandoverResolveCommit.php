<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\HandoverRejectionAdminResolutionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Übergabe-Auflösung speichern')]
class RejectedHandoverResolveCommit extends Component
{
    public Handover $handover;

    public function mount(Handover $handover): void
    {
        $this->authorize('manage-app-assets');

        $this->handover = $handover;

        $key = HandoverRejectionAdminResolutionService::PENDING_RESOLUTION_SESSION_KEY;
        $pending = session()->get($key);

        if (! is_array($pending)
            || (int) ($pending['handover_id'] ?? 0) !== $handover->id
            || (int) ($pending['admin_user_id'] ?? 0) !== (int) auth()->id()) {
            session()->forget($key);
            session()->flash('error', 'Ungültige oder abgelaufene Aktion. Bitte das Formular erneut ausfüllen.');

            $this->redirect(route('apps.assets.admin.rejected-handover.resolve', $handover), navigate: true);

            return;
        }

        if (! $handover->isRejected() || $handover->isConfirmed()) {
            session()->forget($key);
            session()->flash('message', 'Diese Übergabe kann nicht mehr aufgelöst werden.');

            $this->redirect(route('apps.assets.admin.rejected-handovers'), navigate: true);

            return;
        }

        session()->forget($key);

        $service = app(HandoverRejectionAdminResolutionService::class);

        try {
            $service->resolve(
                $handover,
                (int) auth()->id(),
                (string) $pending['resolution'],
                isset($pending['new_owner_user_id']) ? (int) $pending['new_owner_user_id'] : null,
                isset($pending['location']) ? (string) $pending['location'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            $this->redirect(route('apps.assets.admin.rejected-handover.resolve', $handover), navigate: true);

            return;
        }

        session()->flash('message', 'Die abgelehnte Übergabe wurde bearbeitet und das Asset aktualisiert.');

        $this->redirect(route('apps.assets.admin.rejected-handovers'), navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.rejected-handover-resolve-commit');
    }
}
