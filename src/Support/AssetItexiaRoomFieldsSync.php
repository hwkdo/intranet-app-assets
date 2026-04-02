<?php

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\SeventhingsLaravel\Models\Asset as ItexiaAsset;
use Hwkdo\SeventhingsLaravel\Support\ItexiaRoomInspection;

/**
 * Schreibt Ist-/Soll-Raum aus einer Seventhings-Objektantwort in die Intranet-Asset-Spalten.
 */
final class AssetItexiaRoomFieldsSync
{
    public static function applyFromItexiaAsset(Asset $asset, ItexiaAsset $itexiaAsset, bool $save = true): void
    {
        $asset->itexia_actual_room_id = ItexiaRoomInspection::normalizeRoomIdFromItexiaAsset($itexiaAsset);
        $asset->itexia_target_room_id = ItexiaRoomInspection::normalizeTargetRoomIdFromItexiaAsset($itexiaAsset);
        $asset->itexia_rooms_synced_at = now();

        if ($save) {
            $asset->save();
        }
    }
}
