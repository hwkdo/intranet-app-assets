<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

/**
 * Bestätigungskanäle für den Admin-„Übergeben“-Wizard (Schritt 1).
 */
final class AdminHandoverChannel
{
    public const Self = 'self';

    public const PasswordNow = 'password_now';

    public const SignopadZentrale = 'signopad_zentrale';

    /**
     * @return list<string>
     */
    public static function selectableValues(): array
    {
        return [self::Self, self::PasswordNow, self::SignopadZentrale];
    }

    /**
     * @return list<string>
     */
    public static function allValues(): array
    {
        return [self::Self, self::PasswordNow, self::SignopadZentrale];
    }
}
