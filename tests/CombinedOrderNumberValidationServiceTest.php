<?php

use Hwkdo\IntranetAppAssets\Services\CombinedOrderNumberValidationService;
use Hwkdo\IntranetAppAssets\Services\LegacyOrderNumberValidationService;
use Hwkdo\IntranetAppAssets\Services\LocalOrderNumberValidationService;

/**
 * Gibt eine Nummer zurück, die dem konfigurierten Format entspricht.
 * Da die Tests im Package-Context laufen (nicht production), beginnt sie mit '1'.
 */
function validBenNumber(): string
{
    return '112345678';
}

function makeCombinedService(?string $legacyError, ?string $localError): CombinedOrderNumberValidationService
{
    $legacyMock = \Mockery::mock(LegacyOrderNumberValidationService::class);
    $legacyMock->shouldReceive('getValidationError')->andReturn($legacyError);

    $localMock = \Mockery::mock(LocalOrderNumberValidationService::class);
    $localMock->shouldReceive('getValidationError')->andReturn($localError);

    return new CombinedOrderNumberValidationService($legacyMock, $localMock);
}

it('returns null for empty string without consulting sub-services', function () {
    $legacyMock = \Mockery::mock(LegacyOrderNumberValidationService::class);
    $legacyMock->shouldNotReceive('getValidationError');

    $localMock = \Mockery::mock(LocalOrderNumberValidationService::class);
    $localMock->shouldNotReceive('getValidationError');

    $service = new CombinedOrderNumberValidationService($legacyMock, $localMock);

    expect($service->getValidationError(''))->toBeNull();
    expect($service->getValidationError('   '))->toBeNull();
});

it('returns format error without consulting sub-services', function () {
    $legacyMock = \Mockery::mock(LegacyOrderNumberValidationService::class);
    $legacyMock->shouldNotReceive('getValidationError');

    $localMock = \Mockery::mock(LocalOrderNumberValidationService::class);
    $localMock->shouldNotReceive('getValidationError');

    $service = new CombinedOrderNumberValidationService($legacyMock, $localMock);
    $error = $service->getValidationError('12345');
    expect($error)->toContain('Ungültiges Format');
});

it('returns null when number exists only in legacy', function () {
    $service = makeCombinedService(null, 'Die Bestellnummer existiert nicht.');
    expect($service->getValidationError(validBenNumber()))->toBeNull();
});

it('returns null when number exists only in local intranet v3', function () {
    $service = makeCombinedService('Die Bestellnummer existiert nicht.', null);
    expect($service->getValidationError(validBenNumber()))->toBeNull();
});

it('returns null when number exists in both systems', function () {
    $service = makeCombinedService(null, null);
    expect($service->getValidationError(validBenNumber()))->toBeNull();
});

it('returns error when number exists in neither system', function () {
    $service = makeCombinedService('Die Bestellnummer existiert nicht.', 'Die Bestellnummer existiert nicht.');
    $error = $service->getValidationError(validBenNumber());
    expect($error)->not()->toBeNull()
        ->and($error)->toContain('weder');
});
