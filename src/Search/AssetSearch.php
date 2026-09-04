<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Search;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class AssetSearch
{
    /**
     * @return Collection<int, Asset>
     */
    public static function query(string $query, Authenticatable $user, int $limit): Collection
    {
        $builder = Asset::search($query);

        if (! self::canManageAssets($user)) {
            $builder->where('user_id', (int) $user->getAuthIdentifier());
        }

        return $builder
            ->query(function ($eloquent) use ($user): void {
                $eloquent->with(['type', 'vendor', 'owner']);

                if (! self::canManageAssets($user)) {
                    $eloquent->where('user_id', $user->getAuthIdentifier());
                }
            })
            ->take($limit)
            ->get();
    }

    private static function canManageAssets(Authenticatable $user): bool
    {
        if (! method_exists($user, 'can')) {
            return false;
        }

        return $user->can('manage-app-assets');
    }
}
