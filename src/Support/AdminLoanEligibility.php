<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Models\Asset;

/**
 * Admin-„Verleihen“ nur für echte Lager-Assets ohne offene Rückgabe.
 */
final class AdminLoanEligibility
{
    public static function isEligible(Asset $asset): bool
    {
        if ($asset->trashed() || $asset->is_missing) {
            return false;
        }

        if (! $asset->is_in_stock) {
            return false;
        }

        return ! AdminHandoverEligibility::hasOpenReturn($asset);
    }
}
