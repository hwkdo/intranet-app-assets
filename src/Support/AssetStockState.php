<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Models\Asset;

/**
 * Erzwingt Invarianten für „Auf Lager“ (is_in_stock).
 *
 * Auf Lager ist ein manuelles Flag — nicht identisch mit „ohne Besitzer“.
 * Es wird automatisch entfernt, wenn ein Besitzer zugewiesen wird oder
 * Vermisst-/Klärung-Flags gesetzt sind.
 */
final class AssetStockState
{
    public static function enforceInvariants(Asset $asset): void
    {
        if ($asset->user_id !== null || $asset->is_missing || $asset->is_clarification) {
            $asset->is_in_stock = false;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function enforceInvariantsOnAttributes(array &$attributes): void
    {
        $hasOwner = filled($attributes['user_id'] ?? null);
        $isMissing = (bool) ($attributes['is_missing'] ?? false);
        $isClarification = (bool) ($attributes['is_clarification'] ?? false);

        if ($hasOwner || $isMissing || $isClarification) {
            $attributes['is_in_stock'] = false;
        }
    }
}
