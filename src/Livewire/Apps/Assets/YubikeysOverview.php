<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Exports\YubikeysTableExport;
use Hwkdo\IntranetAppAssets\Services\YubikeyOverviewService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Layout('components.layouts.app')]
#[Title('Yubikeys')]
class YubikeysOverview extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'ohne')]
    public bool $onlyWithoutYubikey = false;

    public function mount(): void
    {
        $this->authorize('manage-app-assets');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyWithoutYubikey(): void
    {
        $this->resetPage();
    }

    public function exportExcelAll(YubikeyOverviewService $service): BinaryFileResponse
    {
        $this->authorize('manage-app-assets');

        return Excel::download(
            new YubikeysTableExport($service->getExportRows()),
            'yubikeys-alle.xlsx',
        );
    }

    public function exportExcelFiltered(YubikeyOverviewService $service): BinaryFileResponse
    {
        $this->authorize('manage-app-assets');

        return Excel::download(
            new YubikeysTableExport($service->getExportRows($this->onlyWithoutYubikey, $this->search)),
            'yubikeys-gefiltert.xlsx',
        );
    }

    public function render(YubikeyOverviewService $service): View
    {
        return view('intranet-app-assets::livewire.apps.assets.yubikeys', [
            'users' => $service->paginateActiveUsers($this->onlyWithoutYubikey, $this->search),
        ]);
    }
}
