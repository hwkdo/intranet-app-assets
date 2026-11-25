<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Observers;

use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Support\AssetDashboardBroadcaster;

class AssetReturnDashboardBroadcastObserver
{
    public function __construct(
        private AssetDashboardBroadcaster $broadcaster,
    ) {}

    public function saved(AssetReturn $return): void
    {
        $this->broadcaster->returnSaved($return);
    }
}
