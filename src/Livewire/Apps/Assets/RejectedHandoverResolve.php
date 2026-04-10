<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\HandoverRejectionAdminResolutionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Abgelehnte Übergabe bearbeiten')]
class RejectedHandoverResolve extends Component
{
    public Handover $handover;

    public bool $acknowledgeNotWithUser = false;

    public string $resolution = HandoverRejectionAdminResolutionService::ResolutionNewOwner;

    public ?string $newOwnerUserId = null;

    public string $location = '';

    public function mount(Handover $handover): void
    {
        $this->authorize('manage-app-assets');

        if (! $handover->isRejected()) {
            session()->flash('message', 'Diese Übergabe ist nicht als abgelehnt markiert.');
            $this->redirect(route('apps.assets.admin.handovers', ['filter' => 'rejected']), navigate: true);
        }
        if ($handover->isConfirmed()) {
            session()->flash('message', 'Diese Übergabe ist bestätigt.');
            $this->redirect(route('apps.assets.admin.handovers', ['filter' => 'rejected']), navigate: true);
        }

        $handover->load(['recipient', 'issuer']);
        $asset = null;
        if ($handover->asset_id !== null) {
            $asset = Asset::query()
                ->withTrashed()
                ->with(['type', 'vendor'])
                ->find($handover->asset_id);
        }
        $handover->setRelation('asset', $asset);

        $this->handover = $handover;
    }

    public function submit(): void
    {
        $this->authorize('manage-app-assets');

        $rules = [
            'acknowledgeNotWithUser' => ['accepted'],
            'resolution' => ['required', 'string', 'in:'.HandoverRejectionAdminResolutionService::ResolutionNewOwner.','.HandoverRejectionAdminResolutionService::ResolutionSetLocation.','.HandoverRejectionAdminResolutionService::ResolutionMarkMissing],
        ];

        if ($this->resolution === HandoverRejectionAdminResolutionService::ResolutionNewOwner) {
            $rules['newOwnerUserId'] = ['required', 'exists:users,id'];
        }
        if ($this->resolution === HandoverRejectionAdminResolutionService::ResolutionSetLocation) {
            $rules['location'] = ['required', 'string', 'min:1', 'max:255'];
        }

        $this->validate($rules);

        $rawNewOwner = $this->newOwnerUserId;
        $newOwnerId = $rawNewOwner !== null && $rawNewOwner !== '' ? (int) $rawNewOwner : null;

        Session::put(HandoverRejectionAdminResolutionService::PENDING_RESOLUTION_SESSION_KEY, [
            'handover_id' => $this->handover->id,
            'admin_user_id' => auth()->id(),
            'resolution' => $this->resolution,
            'new_owner_user_id' => $this->resolution === HandoverRejectionAdminResolutionService::ResolutionNewOwner ? $newOwnerId : null,
            'location' => $this->resolution === HandoverRejectionAdminResolutionService::ResolutionSetLocation ? trim($this->location) : null,
        ]);

        $this->redirect(route('apps.assets.admin.rejected-handover.resolve-commit', $this->handover), navigate: false);
    }

    public function render(): View
    {
        $users = User::query()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        return view('intranet-app-assets::livewire.apps.assets.rejected-handover-resolve', [
            'users' => $users,
        ]);
    }
}
