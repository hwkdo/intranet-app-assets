<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;

final class InventarMailRecipients
{
    public const DEFAULT_ADDRESS = 'asset@hwk-do.de';

    /**
     * @return list<string>
     */
    public static function resolve(): array
    {
        return self::parse(self::raw());
    }

    private static function raw(): string
    {
        $settings = IntranetAppAssetsSettings::current()?->settings;
        if ($settings instanceof AppSettings) {
            return trim($settings->empfaengerInventarMails);
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private static function parse(string $raw): array
    {
        if ($raw === '') {
            return [self::DEFAULT_ADDRESS];
        }

        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $valid = [];
        foreach ($parts as $part) {
            $email = trim((string) $part);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $email;
            }
        }

        return $valid !== [] ? $valid : [self::DEFAULT_ADDRESS];
    }
}
