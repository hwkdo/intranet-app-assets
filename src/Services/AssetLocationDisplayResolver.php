<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;

/**
 * Anzeige-Logik für Standort/Raum auf Asset-Detailseiten:
 * Pool-Assets zeigen den Asset-Standort, zugewiesene Assets den Raum/Standort des Besitzers.
 */
class AssetLocationDisplayResolver
{
    public const SOURCE_POOL = 'pool_location';

    public const SOURCE_OWNER_RAUM = 'owner_raum';

    public const SOURCE_OWNER_STANDORT = 'owner_standort';

    public const SOURCE_NONE = 'none';

    /**
     * @return array{value: ?string, label: string, hint: ?string, source: string}
     */
    public static function resolve(Asset $asset): array
    {
        $asset->loadMissing(['owner.standort']);

        $owner = $asset->owner;
        if ($owner !== null) {
            $raum = trim((string) ($owner->raum ?? ''));
            if ($raum !== '') {
                return [
                    'value' => $raum,
                    'label' => 'Standort / Raum',
                    'hint' => 'Raum des Besitzers (Stammdaten)',
                    'source' => self::SOURCE_OWNER_RAUM,
                ];
            }

            $standortName = trim((string) ($owner->standort?->name ?? ''));
            if ($standortName !== '') {
                return [
                    'value' => $standortName,
                    'label' => 'Standort / Raum',
                    'hint' => 'Standort des Besitzers (Stammdaten)',
                    'source' => self::SOURCE_OWNER_STANDORT,
                ];
            }

            return [
                'value' => null,
                'label' => 'Standort / Raum',
                'hint' => 'Beim Besitzer ist kein Raum oder Standort hinterlegt.',
                'source' => self::SOURCE_NONE,
            ];
        }

        $location = trim((string) ($asset->location ?? ''));
        if ($location !== '') {
            return [
                'value' => $location,
                'label' => 'Standort',
                'hint' => 'Pool-Standort (Asset ohne Besitzer)',
                'source' => self::SOURCE_POOL,
            ];
        }

        return [
            'value' => null,
            'label' => 'Standort',
            'hint' => null,
            'source' => self::SOURCE_NONE,
        ];
    }
}
