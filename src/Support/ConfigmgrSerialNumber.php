<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

final class ConfigmgrSerialNumber
{
    /**
     * @var list<string>
     */
    private const PLACEHOLDERS = [
        'TOBEFILLEDBYOEM',
        'DEFAULTSTRING',
        'SYSTEMSERIALNUMBER',
        'NONE',
        'NOTSPECIFIED',
        'CHASSISSERIALNUMBER',
        '0',
        '00000000',
        'INVALID',
    ];

    public static function mismatches(?string $assetSerial, ?string $configmgrSerial): bool
    {
        $sccm = self::normalize($configmgrSerial);
        if ($sccm === '' || self::isPlaceholder($sccm)) {
            return false;
        }

        $asset = self::normalize($assetSerial);
        if ($asset === '') {
            return false;
        }

        return $asset !== $sccm;
    }

    public static function normalize(?string $value): string
    {
        $value = strtoupper(trim((string) $value));

        return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
    }

    public static function isPlaceholder(string $normalized): bool
    {
        if ($normalized === '' || str_contains($normalized, 'XXXX')) {
            return true;
        }

        return in_array($normalized, self::PLACEHOLDERS, true);
    }
}
