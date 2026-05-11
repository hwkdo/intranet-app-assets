<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Contracts\OrderNumberValidationServiceInterface;

class CombinedOrderNumberValidationService implements OrderNumberValidationServiceInterface
{
    public function __construct(
        protected LegacyOrderNumberValidationService $legacyService,
        protected LocalOrderNumberValidationService $localService,
    ) {}

    public function getValidationError(string $number): ?string
    {
        $number = trim($number);

        if ($number === '') {
            return null;
        }

        if (! LegacyOrderNumberValidationService::isValidFormat($number)) {
            return 'Ungültiges Format. '.LegacyOrderNumberValidationService::getFormatDescription();
        }

        $legacyError = $this->legacyService->getValidationError($number);
        $localError = $this->localService->getValidationError($number);

        if ($legacyError === null || $localError === null) {
            return null;
        }

        return 'Die Bestellnummer existiert weder im Legacy-Intranet noch im Intranet V3.';
    }
}
