<?php

namespace Hwkdo\IntranetAppAssets\Observers;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Services\AssetOwnerHandoverAutomationService;

class AssetOwnerHandoverObserver
{
    public function __construct(
        private AssetOwnerHandoverAutomationService $handoverAutomation,
    ) {}

    public function saved(Asset $asset): void
    {
        if ($asset->user_id === null) {
            return;
        }

        if ($asset->wasRecentlyCreated || $asset->wasChanged('user_id')) {
            $this->handoverAutomation->ensureAnyHandoverForCurrentOwner($asset);
        }
    }
}
