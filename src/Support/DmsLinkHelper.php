<?php

namespace Hwkdo\IntranetAppAssets\Support;

use Illuminate\Support\Str;

class DmsLinkHelper
{
    /**
     * Liefert die Basis-URL aus der DMS-Such-URL (Suffix /sr/?fulltext= entfernt).
     */
    public static function baseUrlFromDmsSearchUrl(string $dmsSearchUrl): string
    {
        $base = Str::before($dmsSearchUrl, '/sr/?fulltext=');

        if ($base === $dmsSearchUrl || trim($base) === '') {
            return '';
        }

        return rtrim(trim($base), '/');
    }

    /**
     * Liefert die vollständige URL für eine Rechnungsnummer (…/o2/{nummer}) oder null.
     */
    public static function invoiceUrl(string $baseUrl, ?string $invoiceNumber): ?string
    {
        $baseUrl = trim($baseUrl);
        $invoiceNumber = $invoiceNumber !== null ? trim($invoiceNumber) : '';

        if ($baseUrl === '' || $invoiceNumber === '') {
            return null;
        }

        return rtrim($baseUrl, '/').'/o2/'.$invoiceNumber;
    }

    /**
     * Liefert die vollständige URL für eine Bestellnummer (…/sr/?fulltext={nummer}) oder null.
     */
    public static function orderNumberUrl(string $baseUrl, ?string $orderNumber): ?string
    {
        $baseUrl = trim($baseUrl);
        $orderNumber = $orderNumber !== null ? trim($orderNumber) : '';

        if ($baseUrl === '' || $orderNumber === '') {
            return null;
        }

        return rtrim($baseUrl, '/').'/sr/?fulltext='.rawurlencode($orderNumber);
    }
}
