<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Observers\AssetHistoryObserver;

it('reindexes the related asset when history entry changes', function () {
    $asset = new class extends Asset
    {
        public bool $searchableCalled = false;

        public function searchable()
        {
            $this->searchableCalled = true;

            return null;
        }
    };

    $history = new AssetHistory();
    $history->setRelation('asset', $asset);

    $observer = new AssetHistoryObserver();
    $observer->created($history);

    expect($asset->searchableCalled)->toBeTrue();
});
