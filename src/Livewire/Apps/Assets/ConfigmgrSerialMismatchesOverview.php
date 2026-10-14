<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Livewire\Concerns\SortsAssetTable;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Hwkdo\IntranetAppAssets\Support\ConfigmgrSerialMismatches;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Falsche Seriennummern')]
class ConfigmgrSerialMismatchesOverview extends Component
{
    use SortsAssetTable;
    use WithPagination;

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['display_name', 'serial_number', 'configmgr_serial_number', 'type', 'owner', 'created_at'];
    }

    public function mount(): void
    {
        $this->authorize('manage-app-assets');
    }

    public function adoptConfigmgrSerial(int $assetId): void
    {
        $this->authorize('manage-app-assets');

        $asset = Asset::query()->findOrFail($assetId);
        $sccmSerial = trim((string) $asset->configmgr_serial_number);

        if ($sccmSerial === '' || ! $asset->configmgrSerialMismatches()) {
            return;
        }

        AssetAuditContext::runWith('sccm-seriennummer-uebernehmen', function () use ($asset, $sccmSerial): void {
            $asset->serial_number = $sccmSerial;
            $asset->save();
        });

        Flux::toast('Seriennummer aus SCCM übernommen.', variant: 'success');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.configmgr-serial-mismatches-overview', [
            'assets' => $this->assets(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Asset>
     */
    protected function assets(): LengthAwarePaginator
    {
        $sorted = $this->sortAssets(ConfigmgrSerialMismatches::collect(
            Asset::query()->with(['type', 'vendor', 'owner.standort']),
        ));

        $perPage = 25;
        $pageName = 'page';
        $page = $this->getPage($pageName);

        return (new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        ))->withQueryString();
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return Collection<int, Asset>
     */
    private function sortAssets(Collection $assets): Collection
    {
        $column = $this->activeSortColumn();
        if ($column === 'created_at') {
            $byCreatedAt = fn (Asset $asset): int => $asset->created_at?->getTimestamp() ?? 0;

            return ($this->activeSortDirection() === 'desc'
                ? $assets->sortByDesc($byCreatedAt)
                : $assets->sortBy($byCreatedAt))->values();
        }

        $callback = function (Asset $asset) use ($column): string {
            return match ($column) {
                'serial_number' => (string) $asset->serial_number,
                'configmgr_serial_number' => (string) $asset->configmgr_serial_number,
                'type' => (string) ($asset->type?->name ?? ''),
                'owner' => (string) ($asset->owner?->name ?? ''),
                default => $asset->display_name,
            };
        };

        $sorted = $this->activeSortDirection() === 'desc'
            ? $assets->sortByDesc($callback, SORT_NATURAL | SORT_FLAG_CASE)
            : $assets->sortBy($callback, SORT_NATURAL | SORT_FLAG_CASE);

        return $sorted->values();
    }
}
