<?php

namespace Hwkdo\IntranetAppAssets\Contracts;

interface OrderNumberValidationServiceInterface
{
    /**
     * Liefert eine Fehlermeldung für die angegebene Bestellnummer oder null wenn gültig.
     */
    public function getValidationError(string $number): ?string;
}
