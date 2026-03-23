<?php

use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\OpenHandoverAdminResolutionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Offene Übergabe speichern')] class extends Component
{
    public Handover $handover;

    public function mount(Handover $handover): void
    {
        $this->authorize('manage-app-assets');

        $this->handover = $handover;

        $key = OpenHandoverAdminResolutionService::PENDING_RESOLUTION_SESSION_KEY;
        $pending = session()->get($key);

        if (! is_array($pending)
            || (int) ($pending['handover_id'] ?? 0) !== $handover->id
            || (int) ($pending['admin_user_id'] ?? 0) !== (int) auth()->id()) {
            session()->forget($key);
            session()->flash('error', 'Ungültige oder abgelaufene Aktion. Bitte das Formular erneut ausfüllen.');

            $this->redirect(route('apps.assets.admin.open-handover.resolve', $handover), navigate: true);

            return;
        }

        if ($handover->isConfirmed() || $handover->isRejected()) {
            session()->forget($key);
            session()->flash('message', 'Diese Übergabe kann nicht mehr aufgelöst werden.');

            $this->redirect(route('apps.assets.admin.open-handovers'), navigate: true);

            return;
        }

        session()->forget($key);

        $service = app(OpenHandoverAdminResolutionService::class);

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

            $this->redirect(route('apps.assets.admin.open-handover.resolve', $handover), navigate: true);

            return;
        }

        session()->flash('message', 'Die offene Übergabe wurde aufgelöst und das Asset aktualisiert.');

        $this->redirect(route('apps.assets.admin.open-handovers'), navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.open-handover-resolve-commit');
    }
}; ?>
<div>
    <x-intranet-app-assets::assets-layout heading="Speichern" subheading="Auflösung wird übernommen …">
        <div class="flex items-center justify-center py-12">
            <svg class="h-8 w-8 animate-spin text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
