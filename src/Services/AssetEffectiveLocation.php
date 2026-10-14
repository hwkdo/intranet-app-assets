<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Asset;

/**
 * Effektiver Standort wie {@see AssetLocationDisplayResolver}, aber aus user_id + Pool-Standort,
 * damit Vorher/Nachher ohne die geladene Owner-Relation verglichen werden kann.
 */
final class AssetEffectiveLocation
{
    /**
     * @return array{value: ?string, source: string}
     */
    public static function fromAsset(Asset $asset): array
    {
        return self::resolve(
            self::nullableUserId($asset->user_id),
            $asset->location,
        );
    }

    /**
     * @return array{value: ?string, source: string}
     */
    public static function resolve(?int $userId, mixed $poolLocation): array
    {
        if ($userId !== null && $userId > 0) {
            $owner = User::query()->with('standort')->find($userId);
            if ($owner !== null) {
                $raum = trim((string) ($owner->raum ?? ''));
                if ($raum !== '') {
                    return [
                        'value' => $raum,
                        'source' => AssetLocationDisplayResolver::SOURCE_OWNER_RAUM,
                    ];
                }

                $standortName = trim((string) ($owner->standort?->name ?? ''));
                if ($standortName !== '') {
                    return [
                        'value' => $standortName,
                        'source' => AssetLocationDisplayResolver::SOURCE_OWNER_STANDORT,
                    ];
                }

                return [
                    'value' => null,
                    'source' => AssetLocationDisplayResolver::SOURCE_NONE,
                ];
            }
        }

        $location = trim((string) ($poolLocation ?? ''));
        if ($location !== '') {
            return [
                'value' => $location,
                'source' => AssetLocationDisplayResolver::SOURCE_POOL,
            ];
        }

        return [
            'value' => null,
            'source' => AssetLocationDisplayResolver::SOURCE_NONE,
        ];
    }

    public static function nullableUserId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
