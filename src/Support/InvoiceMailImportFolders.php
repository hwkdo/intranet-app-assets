<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

final class InvoiceMailImportFolders
{
    /**
     * Posteingang, der Unterordner eInvoice und Gelöschte Objekte/Elemente sind vorausgewählt.
     */
    public static function shouldPreselect(string $name, int $depth): bool
    {
        $normalized = self::normalize($name);

        if ($depth === 0 && in_array($normalized, ['posteingang', 'inbox'], true)) {
            return true;
        }

        if ($normalized === 'einvoice') {
            return true;
        }

        return str_starts_with($normalized, 'geloeschte');
    }

    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = strtr($name, [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
        ]);

        return (string) preg_replace('/[^a-z0-9]/', '', $name);
    }
}
