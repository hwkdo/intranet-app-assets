<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Illuminate\Support\Collection;

class HandoverSupersessionService
{
    public function supersedeAllActiveForAsset(Asset $asset, ?int $adminUserId, string $reason): void
    {
        Handover::query()
            ->where('asset_id', $asset->id)
            ->active()
            ->get()
            ->each(fn (Handover $handover): mixed => $this->supersede($handover, $adminUserId, $reason));
    }

    /**
     * Beendet bestätigte/abgelehnte aktive Übergaben vor einem neuen offenen Zyklus.
     * Offene Übergaben bleiben erhalten.
     */
    public function supersedeConfirmedAndRejectedForAsset(Asset $asset, ?int $adminUserId, string $reason): void
    {
        Handover::query()
            ->where('asset_id', $asset->id)
            ->active()
            ->where(function ($query): void {
                $query
                    ->whereNotNull('confirmed_at')
                    ->orWhereNotNull('rejected_at');
            })
            ->get()
            ->each(fn (Handover $handover): mixed => $this->supersede($handover, $adminUserId, $reason));
    }

    public function supersede(Handover $handover, ?int $adminUserId, string $reason): void
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

    /**
     * Aktive bestätigte Übergaben, die nicht mehr zum aktuellen Asset-Lifecycle passen.
     *
     * @return Collection<int, Handover>
     */
    public function staleConfirmedHandovers(): Collection
    {
        $openAssetIds = Handover::query()
            ->open()
            ->pluck('asset_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->all();

        return Handover::query()
            ->active()
            ->whereNotNull('confirmed_at')
            ->whereNull('rejected_at')
            ->with(['asset', 'assetReturns'])
            ->orderBy('id')
            ->get()
            ->filter(function (Handover $handover) use ($openAssetIds): bool {
                $asset = $handover->asset;
                if ($asset === null) {
                    return true;
                }

                if ($asset->is_in_stock || $asset->user_id === null) {
                    return true;
                }

                if ((int) $handover->recipient_user_id !== (int) $asset->user_id) {
                    return true;
                }

                if (in_array((int) $asset->id, $openAssetIds, true)) {
                    return true;
                }

                return false;
            })
            ->values();
    }

    private function closePendingReturnsForHandover(Handover $handover, ?int $adminUserId, string $reason): void
    {
        $actorId = $adminUserId
            ?? $handover->issuer_user_id
            ?? $handover->recipient_user_id;

        $handover->assetReturns()
            ->whereNull('completed_at')
            ->get()
            ->each(function (AssetReturn $assetReturn) use ($actorId, $reason): void {
                $now = now();

                $assetReturn->update([
                    'recipient_user_id' => $actorId,
                    'received_confirmed_at' => $assetReturn->received_confirmed_at ?? $now,
                    'completed_at' => $now,
                ]);

                if ($actorId !== null) {
                    $assetReturn->notes()->create([
                        'note' => 'Rückgabe adminseitig geschlossen (Übergabe ersetzt): '.$reason,
                        'user_id' => $actorId,
                    ]);
                }
            });
    }
}
