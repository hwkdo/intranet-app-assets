<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Assets vermisst')]
class MissingAssetsOverview extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('manage-app-assets');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.missing-assets-overview', [
            'assets' => $this->assets(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<Asset>
     */
    protected function assets(): LengthAwarePaginator
    {
        return Asset::query()
            ->where('is_missing', true)
            ->with(['type', 'vendor', 'owner'])
            ->orderByDesc('updated_at')
            ->paginate(25);
    }
}
