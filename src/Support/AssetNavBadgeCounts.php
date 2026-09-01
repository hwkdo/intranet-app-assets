<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Models\Asset;

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
}
