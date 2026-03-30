<?php

namespace Hwkdo\IntranetAppAssets\Support;

class AssetAuditContext
{
    /** @var list<string> */
    private static array $sourceStack = [];

    public static function source(): ?string
    {
        if (self::$sourceStack === []) {
            return null;
        }

        return self::$sourceStack[array_key_last(self::$sourceStack)];
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public static function runWith(string $source, callable $callback): mixed
    {
        self::$sourceStack[] = $source;

        try {
            return $callback();
        } finally {
            array_pop(self::$sourceStack);
        }
    }
}
