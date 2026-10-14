<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

/**
 * Unterdrückt Inventar-Mails bei Massenläufen (Legacy-Import, Mapping, Aufräum-Kommandos).
 */
final class AssetItexiaInventoryNotifyContext
{
    private static int $depth = 0;

    public static function suppressed(): bool
    {
        return self::$depth > 0;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function suppress(callable $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;
        }
    }
}
