<?php

declare(strict_types=1);

use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Enums\D3InvoiceVisionLlmProvider;
use Hwkdo\IntranetAppBase\Enums\AiProvider;

it('defaults allowAssetDirectCreate to false', function (): void {
    $settings = new AppSettings;

    expect($settings->allowAssetDirectCreate)->toBeFalse();
});

it('returns null text provider override when not explicitly set', function (): void {
    $settings = new AppSettings(
        d3InvoiceVisionLlmProvider: D3InvoiceVisionLlmProvider::Langdock,
    );

    expect($settings->textProviderOverride())->toBeNull();
});

it('uses explicit ai overrides when configured', function (): void {
    $settings = new AppSettings(
        aiTextProviderOverride: AiProvider::Langdock,
        aiTextModelOverride: 'gpt-5-mini',
        aiImageProviderOverride: AiProvider::OpenAi,
        aiImageModelOverride: 'dall-e-3',
    );

    expect($settings->textProviderOverride())->toBe(AiProvider::Langdock)
        ->and($settings->textModelOverride())->toBe('gpt-5-mini')
        ->and($settings->imageProviderOverride())->toBe(AiProvider::OpenAi)
        ->and($settings->imageModelOverride())->toBe('dall-e-3');
});

it('does not map d3 vision model fields to gateway text model override', function (): void {
    $settings = new AppSettings(
        d3InvoiceVisionModelLangdock: 'gpt-5-mini',
    );

    expect($settings->textModelOverride())->toBeNull();
});
