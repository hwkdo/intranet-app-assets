<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Services\LegacyOrderNumberValidationService;

final class AssetCreateWizardCopy
{
    public static function bestellungCardDescription(AppSettings $settings): string
    {
        $grenze = $settings->wertgrenzeItexia;

        if ($settings->benBenoetigtWennWertKleinerGrenze) {
            return "BEN immer Pflicht. Itexia-ID ab {$grenze} € Pflicht. Rechnungsnr. optional.";
        }

        return "BEN und Itexia-ID ab {$grenze} € Pflicht. Unter {$grenze} € beides optional. Rechnungsnr. optional.";
    }

    public static function beschaffungCardDescription(AppSettings $settings): string
    {
        $grenze = $settings->wertgrenzeItexia;

        return "Rechnungsnr. und Itexia-ID ab {$grenze} € Pflicht. Kein BEN.";
    }

    public static function mobilfunkCardDescription(AppSettings $settings): string
    {
        $grenze = $settings->wertgrenzeItexia;

        return "Ab {$grenze} €: Rechnungsnr. und Itexia-ID Pflicht. Unter {$grenze} €: nur Itexia-ID (Anlage in Itexia nach Speichern).";
    }

    public static function valueStepQuestion(AppSettings $settings): string
    {
        return "Über oder unter {$settings->wertgrenzeItexia} Euro brutto?";
    }

    public static function valueOverCardHeading(AppSettings $settings): string
    {
        return "Ab {$settings->wertgrenzeItexia} € brutto";
    }

    public static function valueUnderCardHeading(AppSettings $settings): string
    {
        return "Unter {$settings->wertgrenzeItexia} € brutto";
    }

    public static function valueOverCardDescription(AppSettings $settings): string
    {
        return "Wert der Hardware laut Rechnung ab {$settings->wertgrenzeItexia} Euro brutto";
    }

    public static function valueUnderCardDescription(AppSettings $settings): string
    {
        return "Wert der Hardware laut Rechnung unter {$settings->wertgrenzeItexia} Euro brutto";
    }

    public static function orderNumberPlaceholder(): string
    {
        $firstDigit = app()->environment('production') ? '3' : '1';

        return 'z. B. '.$firstDigit.'12345678';
    }

    public static function orderNumberRequirementHint(bool $required, AppSettings $settings): string
    {
        $grenze = $settings->wertgrenzeItexia;

        if (! $required) {
            return "Optional bei Wert unter {$grenze} €.";
        }

        if ($settings->benBenoetigtWennWertKleinerGrenze) {
            return 'Bestellnummer aus dem Bestellsystem. Pflicht bei dieser Variante.';
        }

        return "Pflicht bei Wert ab {$grenze} €.";
    }

    public static function orderNumberFormatDescription(): string
    {
        return LegacyOrderNumberValidationService::getFormatDescription().' Wird gegen das Bestellsystem geprüft.';
    }

    public static function invoiceNumberPlaceholder(): string
    {
        return 'z. B. T12345';
    }

    public static function invoiceNumberRequirementHint(bool $required, AppSettings $settings): string
    {
        $grenze = $settings->wertgrenzeItexia;

        if (! $required) {
            return 'Optional.';
        }

        return "Pflicht bei Wert ab {$grenze} €.";
    }

    public static function itexiaIdPlaceholder(): string
    {
        return 'z. B. Inventar-Barcode';
    }

    public static function itexiaIdRequirementHint(
        bool $required,
        string $variant,
        ?bool $valueOverThreshold,
        AppSettings $settings,
    ): string {
        $grenze = $settings->wertgrenzeItexia;

        if ($variant === 'mobilfunkvertrag' && $valueOverThreshold === false) {
            return "Unter {$grenze} €: Itexia-ID Pflicht, Anlage in Itexia erfolgt automatisch nach Speichern.";
        }

        if (! $required) {
            return "Optional bei Wert unter {$grenze} €.";
        }

        return "Pflicht bei Wert ab {$grenze} €.";
    }
}
