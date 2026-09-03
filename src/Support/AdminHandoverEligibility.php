<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Illuminate\Support\Collection;

/**
 * Admin-„Übergeben“ nur bei Lager-Ausgabe oder offener Übergabe — nicht bei Gemeinschaftsgeräten.
 */
final class AdminHandoverEligibility
{
    public static function isEligible(Asset $asset): bool
    {
        if ($asset->trashed() || $asset->is_missing) {
            return false;
        }

        if ($asset->exists && self::hasOpenReturn($asset)) {
            return false;
        }

        if ($asset->is_in_stock) {
            return true;
        }

        if (! $asset->exists || $asset->user_id === null) {
            return false;
        }

        return Handover::query()
            ->where('asset_id', $asset->id)
            ->open()
            ->exists();
    }

    /**
     * Listenzeile ohne Extra-Queries (Maps bereits geladen).
     *
     * @param  Collection<int, mixed>  $pendingReturnsByAssetId
     * @param  Collection<int, mixed>  $openHandoversByAssetId
     */
    public static function isEligibleForListeRow(
        Asset $asset,
        Collection $pendingReturnsByAssetId,
        Collection $openHandoversByAssetId,
    ): bool {
        if ($asset->is_missing) {
            return false;
        }

        if ($pendingReturnsByAssetId->has($asset->id)) {
            return false;
        }

        if ($asset->is_in_stock) {
            return true;
        }

        return $asset->user_id !== null && $openHandoversByAssetId->has($asset->id);
    }

    public static function hasOpenReturn(Asset $asset): bool
    {
        return AssetReturn::query()
            ->open()
            ->whereHas('handover', function ($query) use ($asset): void {
                $query->where('asset_id', $asset->id)->whereNull('superseded_at');
            })
            ->exists();
    }
}
