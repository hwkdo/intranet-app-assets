<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;

class D3InvoiceValidationService
{
    /**
     * Gültiges Format: erstes Zeichen "T", danach nur Ziffern (z. B. T12345).
     */
    public static function isValidFormat(string $number): bool
    {
        $number = trim($number);
        if ($number === '') {
            return true;
        }

        return (bool) preg_match('/^T\d+$/', $number);
    }

    /**
     * Prüft, ob die Rechnungsnummer in D3 existiert und ein Zahlungsbeleg mit Belegtyp "Rechnung" ist.
     * Bei ungültigem Format wird D3 nicht aufgerufen.
     */
    public function isValidInvoiceNumber(string $number): bool
    {
        $number = trim($number);
        if ($number === '') {
            return true;
        }

        if (! static::isValidFormat($number)) {
            return false;
        }

        if (! class_exists(D3Client::class)) {
            return false;
        }

        try {
            $client = app(D3Client::class);
            $raw = $client->SearchResult($number, DocTypeEnum::Zahlungsbeleg, null, 50, true);
            $items = $raw['items'] ?? [];

            foreach ($items as $item) {
                $categoryId = $item['category']['id'] ?? null;
                if ($categoryId !== DocTypeEnum::Zahlungsbeleg->value) {
                    continue;
                }
                $docId = $item['id'] ?? null;
                if (! $docId) {
                    continue;
                }
                $docRaw = $client->getDoc($docId, true);
                $rechnungsnummer = $this->getDisplayProperty($docRaw, '60');
                $belegtyp = $this->getDisplayProperty($docRaw, '82');
                $expectedBelegtyp = config('intranet-app-assets.d3_invoice_belegtyp', 'Rechnung');
                if ($rechnungsnummer !== null && trim((string) $rechnungsnummer) === $number
                    && $belegtyp !== null && trim((string) $belegtyp) === $expectedBelegtyp) {
                    return true;
                }
                if ($docId === $number && $belegtyp !== null && trim((string) $belegtyp) === $expectedBelegtyp) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    /**
     * Liefert eine Fehlermeldung für die angegebene Rechnungsnummer oder null wenn gültig.
     * Für Live-Validierung: zuerst Format, dann D3.
     */
    public function getValidationError(string $number): ?string
    {
        $number = trim($number);
        if ($number === '') {
            return null;
        }

        if (! static::isValidFormat($number)) {
            return 'Das Format der Rechnungsnummer ist ungültig. Erwartet: T gefolgt von Ziffern (z. B. T12345).';
        }

        if (! $this->isValidInvoiceNumber($number)) {
            return 'Die Rechnungsnummer existiert nicht in D3 oder ist kein Zahlungsbeleg vom Typ Rechnung.';
        }

        return null;
    }

    private function getDisplayProperty(array $data, string $propertyId): ?string
    {
        $props = isset($data['displayProperties'])
            ? collect($data['displayProperties'])
            : collect($data['objectProperties'] ?? []);
        $found = $props->where('id', $propertyId)->first();

        if ($found !== null) {
            return $found['value'] ?? null;
        }
        $system = collect($data['systemProperties'] ?? []);
        $sysFound = $system->where('id', $propertyId)->first();

        return $sysFound['value'] ?? null;
    }
}
