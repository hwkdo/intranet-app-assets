<?php

declare(strict_types=1);

use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Support\AssetCreateWizardCopy;

it('beschreibt bestellung-karte abhängig vom ben-setting', function (): void {
    $immerPflicht = new AppSettings(benBenoetigtWennWertKleinerGrenze: true, wertgrenzeItexia: 250);
    $nurAbGrenze = new AppSettings(benBenoetigtWennWertKleinerGrenze: false, wertgrenzeItexia: 250);

    expect(AssetCreateWizardCopy::bestellungCardDescription($immerPflicht))
        ->toBe('BEN immer Pflicht. Itexia-ID ab 250 € Pflicht. Rechnungsnr. optional.')
        ->and(AssetCreateWizardCopy::bestellungCardDescription($nurAbGrenze))
        ->toBe('BEN und Itexia-ID ab 250 € Pflicht. Unter 250 € beides optional. Rechnungsnr. optional.');
});

it('beschreibt beschaffung- und mobilfunk-karten', function (): void {
    $settings = new AppSettings(wertgrenzeItexia: 250);

    expect(AssetCreateWizardCopy::beschaffungCardDescription($settings))
        ->toBe('Rechnungsnr. und Itexia-ID ab 250 € Pflicht. Kein BEN.')
        ->and(AssetCreateWizardCopy::mobilfunkCardDescription($settings))
        ->toBe('Ab 250 €: Rechnungsnr. und Itexia-ID Pflicht. Unter 250 €: nur Itexia-ID (Anlage in Itexia nach Speichern).');
});

it('liefert ben-hinweis passend zu required und setting', function (): void {
    $immerPflicht = new AppSettings(benBenoetigtWennWertKleinerGrenze: true, wertgrenzeItexia: 250);
    $nurAbGrenze = new AppSettings(benBenoetigtWennWertKleinerGrenze: false, wertgrenzeItexia: 250);

    expect(AssetCreateWizardCopy::orderNumberRequirementHint(true, $immerPflicht))
        ->toBe('Bestellnummer aus dem Bestellsystem. Pflicht bei dieser Variante.')
        ->and(AssetCreateWizardCopy::orderNumberRequirementHint(true, $nurAbGrenze))
        ->toBe('Pflicht bei Wert ab 250 €.')
        ->and(AssetCreateWizardCopy::orderNumberRequirementHint(false, $nurAbGrenze))
        ->toBe('Optional bei Wert unter 250 €.');
});

it('verwendet formatbeispiel als placeholder statt regeltext', function (): void {
    expect(AssetCreateWizardCopy::orderNumberPlaceholder())->toStartWith('z. B. ')
        ->and(AssetCreateWizardCopy::invoiceNumberPlaceholder())->toBe('z. B. T12345')
        ->and(AssetCreateWizardCopy::itexiaIdPlaceholder())->toBe('z. B. Inventar-Barcode');
});

it('nutzt die konfigurierte wertgrenze im wert-schritt', function (): void {
    $settings = new AppSettings(wertgrenzeItexia: 300);

    expect(AssetCreateWizardCopy::valueStepQuestion($settings))
        ->toBe('Über oder unter 300 Euro brutto?')
        ->and(AssetCreateWizardCopy::valueOverCardHeading($settings))
        ->toBe('Ab 300 € brutto')
        ->and(AssetCreateWizardCopy::valueUnderCardHeading($settings))
        ->toBe('Unter 300 € brutto');
});
