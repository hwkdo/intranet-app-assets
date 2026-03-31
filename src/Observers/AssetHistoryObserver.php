<?php

namespace Hwkdo\IntranetAppAssets\Observers;

use Hwkdo\IntranetAppAssets\Models\AssetHistory;

class AssetHistoryObserver
{
    public function created(AssetHistory $assetHistory): void
    {
        $assetHistory->asset?->searchable();
    }

    public function updated(AssetHistory $assetHistory): void
    {
        $assetHistory->asset?->searchable();
    }

    public function deleted(AssetHistory $assetHistory): void
    {
        $assetHistory->asset?->searchable();
    }
}
