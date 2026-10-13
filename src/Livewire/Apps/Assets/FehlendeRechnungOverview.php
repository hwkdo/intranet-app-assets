<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Livewire\Concerns\SortsAssetTable;
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
    use SortsAssetTable;
    use WithPagination;

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['display_name', 'serial_number', 'invoice_number', 'order_number', 'created_by'];
    }

    protected function defaultSortColumn(): string
    {
        return 'created_at';
    }

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
        $query = Asset::query()
            ->invoiceNumberPending()
            ->with(['vendor', 'createdBy']);

        return $this->applyAssetSort($query)->paginate(25);
    }
}
