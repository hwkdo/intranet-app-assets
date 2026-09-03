<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

/**
 * Sentinel-Werte für die Besitzer-Auswahl beim Asset-Anlegen.
 *
 * '' = noch nicht gewählt (Pflichtauswahl)
 * 'none' = kein Besitzer (Lager oder Gemeinschaft)
 * sonst = User-ID als String
 */
final class AssetOwnerChoice
{
    public const None = 'none';

    public static function isNone(mixed $value): bool
    {
        return (string) $value === self::None;
    }

    public static function isUnset(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    public static function toUserId(mixed $value): ?int
    {
        if (self::isUnset($value) || self::isNone($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
