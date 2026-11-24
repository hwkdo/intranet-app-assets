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
     * Erlaubte Werte für Belegtyp_ZB (Property 82).
     *
     * @return list<string>
     */
    public static function allowedBelegtypen(): array
    {
        $configured = config('intranet-app-assets.d3_invoice_belegtypen', ['Rechnung', 'Abbuchung', 'Barauszahlung']);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        if (! is_array($configured)) {
            return ['Rechnung', 'Abbuchung', 'Barauszahlung'];
        }

        $types = [];
        foreach ($configured as $type) {
            if (! is_string($type)) {
                continue;
            }
            $type = trim($type);
            if ($type !== '') {
                $types[] = $type;
            }
        }

        return $types;
    }

    public static function allowedBelegtypenLabel(): string
    {
        $types = self::allowedBelegtypen();

        if (count($types) <= 1) {
            return $types[0] ?? 'Rechnung';
        }

        $last = array_pop($types);

        return implode(', ', $types).' oder '.$last;
    }

    /**
     * Prüft, ob die Rechnungsnummer in D3 existiert und ein Zahlungsbeleg mit erlaubtem Belegtyp ist.
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
                if (! $this->belegtypIsAllowed($belegtyp)) {
                    continue;
                }
                if ($rechnungsnummer !== null && trim((string) $rechnungsnummer) === $number) {
                    return true;
                }
                if ($docId === $number) {
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
            return 'Die Rechnungsnummer existiert nicht in D3 oder ist kein Zahlungsbeleg vom Typ '.self::allowedBelegtypenLabel().'.';
        }

        return null;
    }

    private function belegtypIsAllowed(?string $belegtyp): bool
    {
        if ($belegtyp === null) {
            return false;
        }

        return in_array(trim($belegtyp), self::allowedBelegtypen(), true);
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
