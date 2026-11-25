<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Observers;

use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\AssetDashboardBroadcaster;

class HandoverDashboardBroadcastObserver
{
    public function __construct(
        private AssetDashboardBroadcaster $broadcaster,
    ) {}

    public function saved(Handover $handover): void
    {
        $this->broadcaster->handoverSaved($handover);
    }
}
