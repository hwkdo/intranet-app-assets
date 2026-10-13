<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

final class SerialNumberDiff
{
    /**
     * @return array{asset: list<array{text: string, changed: bool}>, sccm: list<array{text: string, changed: bool}>}
     */
    public static function split(string $assetSerial, string $sccmSerial): array
    {
        $assetChars = mb_str_split($assetSerial);
        $sccmChars = mb_str_split($sccmSerial);
        $prefix = 0;
        $maxPrefix = min(count($assetChars), count($sccmChars));

        while ($prefix < $maxPrefix && self::same($assetChars[$prefix], $sccmChars[$prefix])) {
            $prefix++;
        }

        $assetEnd = count($assetChars);
        $sccmEnd = count($sccmChars);

        while ($assetEnd > $prefix && $sccmEnd > $prefix && self::same($assetChars[$assetEnd - 1], $sccmChars[$sccmEnd - 1])) {
            $assetEnd--;
            $sccmEnd--;
        }

        return [
            'asset' => self::parts($assetChars, $prefix, $assetEnd),
            'sccm' => self::parts($sccmChars, $prefix, $sccmEnd),
        ];
    }

    /**
     * @param  list<string>  $chars
     * @return list<array{text: string, changed: bool}>
     */
    private static function parts(array $chars, int $prefix, int $changedEnd): array
    {
        $parts = [];
        $before = implode('', array_slice($chars, 0, $prefix));
        $changed = implode('', array_slice($chars, $prefix, $changedEnd - $prefix));
        $after = implode('', array_slice($chars, $changedEnd));

        if ($before !== '') {
            $parts[] = ['text' => $before, 'changed' => false];
        }

        if ($changed !== '') {
            $parts[] = ['text' => $changed, 'changed' => true];
        }

        if ($after !== '') {
            $parts[] = ['text' => $after, 'changed' => false];
        }

        return $parts;
    }

    private static function same(string $left, string $right): bool
    {
        return mb_strtoupper($left, 'UTF-8') === mb_strtoupper($right, 'UTF-8');
    }
}
