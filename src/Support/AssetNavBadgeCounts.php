<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;

final class AssetNavBadgeCounts
{
    public static function clarificationCount(): int
    {
        return Asset::query()
            ->where('is_clarification', true)
            ->count();
    }

    public static function missingCount(): int
    {
        return Asset::query()
            ->where('is_missing', true)
            ->count();
    }

    public static function pendingReturnsCount(): int
    {
        return AssetReturn::query()
            ->whereNull('completed_at')
            ->whereHas('handover')
            ->count();
    }

    public static function openHandoversCount(): int
    {
        return Handover::query()->open()->count();
    }

    public static function rejectedHandoversCount(): int
    {
        return Handover::query()->rejectedPendingAdmin()->count();
    }

    public static function zentraleHandoversCount(): int
    {
        return Handover::query()->pendingSignopadZentrale()->count();
    }
}
