<?php

namespace Hwkdo\IntranetAppAssets\Services;

use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppAssets\Contracts\OrderNumberValidationServiceInterface;

class LegacyOrderNumberValidationService implements OrderNumberValidationServiceInterface
{
    public function __construct(
        protected IntranetLegacyService $legacyService
    ) {}

    /**
     * Erwartetes Format: genau 9 Ziffern.
     * Production: beginnt mit 3. Sonst: beginnt mit 1.
     */
    public static function isValidFormat(string $number): bool
    {
        $number = trim($number);
        if ($number === '') {
            return true;
        }

        $firstDigit = app()->environment('production') ? '3' : '1';

        return (bool) preg_match('/^'.preg_quote($firstDigit, '/').'\d{8}$/', $number);
    }

    /**
     * Erwartetes Format als Hinweistext (z. B. für die UI).
     */
    public static function getFormatDescription(): string
    {
        $firstDigit = app()->environment('production') ? '3' : '1';

        return '9 Ziffern, beginnend mit '.$firstDigit.' (z. B. '.$firstDigit.'12345678).';
    }

    public function getValidationError(string $number): ?string
    {
        $number = trim($number);
        if ($number === '') {
            return null;
        }

        if (! static::isValidFormat($number)) {
            return 'Ungültiges Format. '.static::getFormatDescription();
        }

        if (! $this->legacyService->orderNumberExists($number)) {
            return 'Die Bestellnummer existiert nicht.';
        }

        return null;
    }
}
