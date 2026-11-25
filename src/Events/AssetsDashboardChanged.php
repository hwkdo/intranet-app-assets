<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AssetsDashboardChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('assets-dashboard-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'assets.dashboard-changed';
    }
}
