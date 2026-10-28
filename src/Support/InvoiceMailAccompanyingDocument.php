<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

/**
 * Begleitdokumente wie AGB sind kein Beleg, auch wenn die Mail eine Rechnung ist.
 */
final class InvoiceMailAccompanyingDocument
{
    /**
     * Dateiname enthält AGB, Geschäftsbedingungen, Datenschutz, Widerruf oder Impressum.
     */
    public static function matchesFilename(string $filename): bool
    {
        $tokens = preg_split('/[^[:alnum:]]+/u', mb_strtolower($filename)) ?: [];

        foreach ($tokens as $token) {
            $token = self::normalize($token);
            if ($token === '') {
                continue;
            }

            if (str_contains($token, 'agb')) {
                return true;
            }

            foreach (['datenschutz', 'widerruf', 'impressum'] as $needle) {
                if ($token === $needle || str_starts_with($token, $needle)) {
                    return true;
                }
            }
        }

        $whole = self::normalize($filename);

        foreach (['geschaeftsbedingungen', 'lieferbedingungen', 'zahlungsbedingungen'] as $needle) {
            if (str_contains($whole, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Der Anfang des Dokumenttexts ist selbst das Begleitdokument, nicht nur ein Hinweis darauf.
     */
    public static function matchesText(string $text): bool
    {
        $head = self::normalize(mb_substr($text, 0, 500));

        foreach ([
            'allgemeinegeschaefts',
            'geschaeftsbedingungen',
            'datenschutzerklaerung',
            'widerrufsbelehrung',
            'allgemeinelieferbedingungen',
        ] as $needle) {
            if (str_contains($head, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
        ]);

        return (string) preg_replace('/[^a-z0-9]/', '', $value);
    }
}
