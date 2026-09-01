<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;

class HandoverSupersessionService
{
    public function supersedeAllActiveForAsset(Asset $asset, int $adminUserId, string $reason): void
    {
        Handover::query()
            ->where('asset_id', $asset->id)
            ->active()
            ->get()
            ->each(fn (Handover $handover): mixed => $this->supersede($handover, $adminUserId, $reason));
    }

    public function supersede(Handover $handover, int $adminUserId, string $reason): void
    {
        if ($handover->isSuperseded()) {
            return;
        }

        $this->closePendingReturnsForHandover($handover, $adminUserId, $reason);

        $handover->update([
            'superseded_at' => now(),
            'superseded_by_user_id' => $adminUserId,
            'superseded_reason' => $reason,
        ]);
    }

    private function closePendingReturnsForHandover(Handover $handover, int $adminUserId, string $reason): void
    {
        $handover->assetReturns()
            ->whereNull('completed_at')
            ->get()
            ->each(function (AssetReturn $assetReturn) use ($adminUserId, $reason): void {
                $now = now();

                $assetReturn->update([
                    'recipient_user_id' => $adminUserId,
                    'received_confirmed_at' => $assetReturn->received_confirmed_at ?? $now,
                    'completed_at' => $now,
                ]);

                $assetReturn->notes()->create([
                    'note' => 'Rückgabe adminseitig geschlossen (Übergabe ersetzt): '.$reason,
                    'user_id' => $adminUserId,
                ]);
            });
    }
}
