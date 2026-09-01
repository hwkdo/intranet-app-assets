<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Services\AssetMissingAdminResolutionService;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Vermisst-Fall bearbeiten')]
class MissingResolve extends Component
{
    public Asset $asset;

    public bool $acknowledgeReview = false;

    public string $resolution = AssetMissingAdminResolutionService::ResolutionClearOnly;

    public ?string $newOwnerUserId = null;

    public string $location = '';

    #[Validate('required|string|min:3|max:5000')]
    public string $note = '';

    public function mount(Asset $asset): void
    {
        $this->authorize('manage-app-assets');

        if ($asset->trashed()) {
            abort(404);
        }

        if (! $asset->is_missing) {
            session()->flash('message', 'Dieses Asset ist nicht als vermisst markiert.');
            $this->redirect(route('apps.assets.admin.missing'), navigate: true);
        }

        $asset->load(['type', 'vendor', 'owner']);
        $this->asset = $asset;
    }

    public function submit(): void
    {
        $this->authorize('manage-app-assets');

        $rules = [
            'acknowledgeReview' => ['accepted'],
            'note' => ['required', 'string', 'min:3', 'max:5000'],
            'resolution' => ['required', 'string', 'in:'.AssetMissingAdminResolutionService::ResolutionClearOnly.','.AssetMissingAdminResolutionService::ResolutionNewOwner.','.AssetMissingAdminResolutionService::ResolutionSetLocation],
        ];

        if ($this->resolution === AssetMissingAdminResolutionService::ResolutionNewOwner) {
            $rules['newOwnerUserId'] = ['required', 'exists:users,id'];
        }
        if ($this->resolution === AssetMissingAdminResolutionService::ResolutionSetLocation) {
            $rules['location'] = ['required', 'string', 'min:1', 'max:255'];
        }

        $this->validate($rules);

        $rawNewOwner = $this->newOwnerUserId;
        $newOwnerId = $rawNewOwner !== null && $rawNewOwner !== '' ? (int) $rawNewOwner : null;

        Session::put(AssetMissingAdminResolutionService::PENDING_RESOLUTION_SESSION_KEY, [
            'asset_id' => $this->asset->id,
            'admin_user_id' => auth()->id(),
            'resolution' => $this->resolution,
            'new_owner_user_id' => $this->resolution === AssetMissingAdminResolutionService::ResolutionNewOwner ? $newOwnerId : null,
            'location' => $this->resolution === AssetMissingAdminResolutionService::ResolutionSetLocation ? trim($this->location) : null,
            'note' => trim($this->note),
        ]);

        $this->redirect(route('apps.assets.admin.missing.resolve-commit', $this->asset), navigate: false);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $users = \App\Models\User::query()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        return view('intranet-app-assets::livewire.apps.assets.missing-resolve', [
            'users' => $users,
        ]);
    }
}
