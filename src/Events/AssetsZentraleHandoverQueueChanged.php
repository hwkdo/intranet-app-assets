<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Events;

use Hwkdo\IntranetAppAssets\Models\Handover;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssetsZentraleHandoverQueueChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public const ACTION_QUEUED = 'queued';

    public const ACTION_CONFIRMED = 'confirmed';

    public const ACTION_REMOVED = 'removed';

    public function __construct(
        public Handover $handover,
        public string $action,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('assets-zentrale-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'handover-queue-changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'handover_id' => $this->handover->id,
            'action' => $this->action,
        ];
    }
}
