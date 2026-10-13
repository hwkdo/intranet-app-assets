<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ConfigmgrSerialMismatches
{
    /**
     * @param  Builder<Asset>  $query
     * @return Builder<Asset>
     */
    public static function constrain(Builder $query): Builder
    {
        return $query
            ->whereNotNull('serial_number')
            ->where('serial_number', '!=', '')
            ->whereNotNull('configmgr_serial_number')
            ->where('configmgr_serial_number', '!=', '');
    }

    /**
     * @param  Builder<Asset>|null  $query
     * @return Collection<int, Asset>
     */
    public static function collect(?Builder $query = null): Collection
    {
        return self::constrain($query ?? Asset::query())
            ->get()
            ->filter(fn (Asset $asset): bool => $asset->configmgrSerialMismatches())
            ->values();
    }

    public static function count(): int
    {
        return self::constrain(Asset::query())
            ->get(['id', 'serial_number', 'configmgr_serial_number'])
            ->filter(fn (Asset $asset): bool => $asset->configmgrSerialMismatches())
            ->count();
    }
}
