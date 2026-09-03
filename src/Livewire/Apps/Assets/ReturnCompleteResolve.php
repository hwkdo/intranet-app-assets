<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Services\AssetReturnAdminCompletionService;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Rückgabe abschließen')]
class ReturnCompleteResolve extends Component
{
    public AssetReturn $assetReturn;

    public bool $acknowledgeReceipt = false;

    public string $resolution = AssetReturnAdminCompletionService::ResolutionNewOwner;

    public ?string $newOwnerUserId = null;

    public string $location = '';

    public function mount(AssetReturn $assetReturn): void
    {
        $this->authorize('manage-app-assets');

        if ($assetReturn->isCompleted()) {
            session()->flash('message', 'Diese Rückgabe ist bereits abgeschlossen.');
            $this->redirect(route('apps.assets.admin.returns.pending'), navigate: true);
        }

        $assetReturn->load([
            'handover.asset.type',
            'handover.asset.vendor',
            'handover.asset.owner.standort',
            'handover.recipient',
            'initiatedBy',
        ]);

        if ($assetReturn->handover === null) {
            session()->flash('error', 'Die zugehörige Übergabe fehlt; diese Rückgabe kann nicht bearbeitet werden.');
            $this->redirect(route('apps.assets.admin.returns.pending'), navigate: true);
        }

        $this->assetReturn = $assetReturn;
    }

    public function submit(): void
    {
        $this->authorize('manage-app-assets');

        $rules = [
            'acknowledgeReceipt' => ['accepted'],
            'resolution' => ['required', 'string', 'in:'.AssetReturnAdminCompletionService::ResolutionNewOwner.','.AssetReturnAdminCompletionService::ResolutionSetLocation],
        ];

        if ($this->resolution === AssetReturnAdminCompletionService::ResolutionNewOwner) {
            $rules['newOwnerUserId'] = ['required', 'exists:users,id'];
        }
        if ($this->resolution === AssetReturnAdminCompletionService::ResolutionSetLocation) {
            $rules['location'] = ['required', 'string', 'min:1', 'max:255'];
        }

        $this->validate($rules);

        $rawNewOwner = $this->newOwnerUserId;
        $newOwnerId = $rawNewOwner !== null && $rawNewOwner !== '' ? (int) $rawNewOwner : null;

        Session::put(AssetReturnAdminCompletionService::PENDING_SESSION_KEY, [
            'asset_return_id' => $this->assetReturn->id,
            'admin_user_id' => auth()->id(),
            'resolution' => $this->resolution,
            'new_owner_user_id' => $this->resolution === AssetReturnAdminCompletionService::ResolutionNewOwner ? $newOwnerId : null,
            'location' => $this->resolution === AssetReturnAdminCompletionService::ResolutionSetLocation ? trim($this->location) : null,
        ]);

        $this->redirect(route('apps.assets.admin.return.complete-commit', $this->assetReturn), navigate: false);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $users = \App\Models\User::query()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        return view('intranet-app-assets::livewire.apps.assets.return-complete-resolve', [
            'users' => $users,
        ]);
    }
}
