<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Fehlende Rechnung')]
class FehlendeRechnungOverview extends Component
{
    use WithPagination;

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.fehlende-rechnung-overview', [
            'assets' => $this->getAssets(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<Asset>
     */
    protected function getAssets(): LengthAwarePaginator
    {
        return Asset::query()
            ->invoiceNumberPending()
            ->with(['vendor', 'createdBy'])
            ->orderBy('created_at')
            ->paginate(25);
    }
}
