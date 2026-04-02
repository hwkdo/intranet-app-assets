<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;

/**
 * Ermittelt den Suchbegriff für die Itexia-Raumzuordnung aus dem Intranet-Asset:
 * zuerst Raum des Besitzers (owner.raum), sonst Standort des Assets (Spalte location).
 */
class AssetItexiaRoomSearchHintResolver
{
    public const SOURCE_OWNER_RAUM = 'owner_raum';

    public const SOURCE_ASSET_LOCATION = 'asset_location';

    public const SOURCE_NONE = 'none';

    /**
     * @return array{hint: string|null, source: string}
     */
    public static function resolve(Asset $asset): array
    {
        $asset->loadMissing('owner');

        $owner = $asset->owner;
        if ($owner !== null) {
            $raum = trim((string) ($owner->raum ?? ''));
            if ($raum !== '') {
                return ['hint' => $raum, 'source' => self::SOURCE_OWNER_RAUM];
            }
        }

        $location = trim((string) ($asset->location ?? ''));
        if ($location !== '') {
            return ['hint' => $location, 'source' => self::SOURCE_ASSET_LOCATION];
        }

        return ['hint' => null, 'source' => self::SOURCE_NONE];
    }
}
