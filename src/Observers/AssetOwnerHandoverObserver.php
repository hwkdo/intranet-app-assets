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
        if ($asset->wasRecentlyCreated) {
            if ($asset->user_id !== null) {
                $this->handoverAutomation->createHandoverForOwnerAssignment($asset);
            }

            return;
        }

        if ($asset->wasChanged('user_id') && $asset->user_id !== null) {
            $this->handoverAutomation->createHandoverForOwnerAssignment($asset);
        }
    }
}
