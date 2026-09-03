<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Services\AssetClarificationAdminResolutionService;
use Hwkdo\IntranetAppAssets\Support\AssetUnownedDeviceType;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Klärfall bearbeiten')]
class ClarificationResolve extends Component
{
    public Asset $asset;

    public bool $acknowledgeReview = false;

    public string $resolution = AssetClarificationAdminResolutionService::ResolutionClearOnly;

    public ?string $newOwnerUserId = null;

    public string $location = '';

    public string $deviceType = '';

    public function mount(Asset $asset): void
    {
        $this->authorize('manage-app-assets');

        if ($asset->trashed()) {
            abort(404);
        }

        if (! $asset->is_clarification) {
            session()->flash('message', 'Dieses Asset ist nicht in Klärung.');
            $this->redirect(route('apps.assets.admin.clarifications'), navigate: true);
        }

        $asset->load(['type', 'vendor', 'owner']);
        $this->asset = $asset;
        $this->deviceType = AssetUnownedDeviceType::defaultForAsset(
            $asset->user_id !== null ? (int) $asset->user_id : null,
            (bool) $asset->is_in_stock,
        );
    }

    public function submit(): void
    {
        $this->authorize('manage-app-assets');

        $rules = [
            'acknowledgeReview' => ['accepted'],
            'resolution' => ['required', 'string', 'in:'.AssetClarificationAdminResolutionService::ResolutionClearOnly.','.AssetClarificationAdminResolutionService::ResolutionNewOwner.','.AssetClarificationAdminResolutionService::ResolutionSetLocation.','.AssetClarificationAdminResolutionService::ResolutionMarkMissing],
        ];

        if ($this->resolution === AssetClarificationAdminResolutionService::ResolutionNewOwner) {
            $rules['newOwnerUserId'] = ['required', 'exists:users,id'];
        }
        if ($this->resolution === AssetClarificationAdminResolutionService::ResolutionSetLocation) {
            $rules['location'] = ['required', 'string', 'min:1', 'max:255'];
            $rules['deviceType'] = ['required', 'string', Rule::in(AssetUnownedDeviceType::values())];
        }

        $this->validate($rules);

        $rawNewOwner = $this->newOwnerUserId;
        $newOwnerId = $rawNewOwner !== null && $rawNewOwner !== '' ? (int) $rawNewOwner : null;

        Session::put(AssetClarificationAdminResolutionService::PENDING_RESOLUTION_SESSION_KEY, [
            'asset_id' => $this->asset->id,
            'admin_user_id' => auth()->id(),
            'resolution' => $this->resolution,
            'new_owner_user_id' => $this->resolution === AssetClarificationAdminResolutionService::ResolutionNewOwner ? $newOwnerId : null,
            'location' => $this->resolution === AssetClarificationAdminResolutionService::ResolutionSetLocation ? trim($this->location) : null,
            'mark_in_stock' => $this->resolution === AssetClarificationAdminResolutionService::ResolutionSetLocation
                ? AssetUnownedDeviceType::toIsInStock($this->deviceType)
                : null,
        ]);

        $this->redirect(route('apps.assets.admin.clarification.resolve-commit', $this->asset), navigate: false);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $users = \App\Models\User::query()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        return view('intranet-app-assets::livewire.apps.assets.clarification-resolve', [
            'users' => $users,
        ]);
    }
}
