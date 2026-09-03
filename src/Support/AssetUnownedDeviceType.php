<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

/**
 * Gerätetyp für Assets ohne persönlichen Besitzer (Formular-Auswahl).
 *
 * '' = noch nicht gewählt (Pflicht bei kein Besitzer)
 * pool = Lager / Pool-Gerät (is_in_stock = true)
 * shared = Gemeinschaftsgerät (is_in_stock = false)
 */
final class AssetUnownedDeviceType
{
    public const Pool = 'pool';

    public const Shared = 'shared';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::Pool, self::Shared];
    }

    public static function isValid(mixed $value): bool
    {
        return in_array((string) $value, self::values(), true);
    }

    public static function isUnset(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    public static function toIsInStock(mixed $value): bool
    {
        return (string) $value === self::Pool;
    }

    public static function fromIsInStock(bool $isInStock): string
    {
        return $isInStock ? self::Pool : self::Shared;
    }

    public static function defaultForAsset(?int $userId, bool $isInStock): string
    {
        if ($userId !== null) {
            return self::Pool;
        }

        return self::fromIsInStock($isInStock);
    }
}
