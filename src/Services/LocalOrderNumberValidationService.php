<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Contracts\OrderNumberValidationServiceInterface;

class LocalOrderNumberValidationService implements OrderNumberValidationServiceInterface
{
    public function getValidationError(string $number): ?string
    {
        $number = trim($number);

        if ($number === '') {
            return null;
        }

        if (! LegacyOrderNumberValidationService::isValidFormat($number)) {
            return 'Ungültiges Format. '.LegacyOrderNumberValidationService::getFormatDescription();
        }

        if (! $this->existsLocally($number)) {
            return 'Die Bestellnummer existiert nicht.';
        }

        return null;
    }

    private function existsLocally(string $number): bool
    {
        /** @var class-string $bestellungClass */
        $bestellungClass = 'Hwkdo\IntranetAppBestellungen\Models\Bestellung';

        if (! class_exists($bestellungClass)) {
            return false;
        }

        return $bestellungClass::query()
            ->where('nummer', $number)
            ->exists();
    }
}
