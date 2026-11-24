<?php

declare(strict_types=1);

use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\IntranetAppAssets\Services\D3InvoiceValidationService;

function fakeD3Zahlungsbeleg(string $number, string $belegtyp, string $docId = 'DOC-1', ?string $rechnungsnummer = null): void
{
    $client = Mockery::mock(D3Client::class);
    $client->shouldReceive('SearchResult')
        ->once()
        ->with($number, DocTypeEnum::Zahlungsbeleg, null, 50, true)
        ->andReturn([
            'items' => [
                [
                    'id' => $docId,
                    'category' => ['id' => DocTypeEnum::Zahlungsbeleg->value],
                ],
            ],
        ]);
    $client->shouldReceive('getDoc')
        ->once()
        ->with($docId, true)
        ->andReturn([
            'displayProperties' => [
                ['id' => '60', 'value' => $rechnungsnummer ?? $number],
                ['id' => '82', 'value' => $belegtyp],
            ],
        ]);

    app()->instance(D3Client::class, $client);
}

it('akzeptiert zahlungsbelege mit erlaubtem belegtyp', function (string $belegtyp): void {
    fakeD3Zahlungsbeleg('T100', $belegtyp);

    expect(app(D3InvoiceValidationService::class)->getValidationError('T100'))->toBeNull();
})->with([
    'Rechnung',
    'Abbuchung',
    'Barauszahlung',
]);

it('lehnt andere belegtypen ab', function (string $belegtyp): void {
    fakeD3Zahlungsbeleg('T100', $belegtyp);

    $error = app(D3InvoiceValidationService::class)->getValidationError('T100');

    expect($error)->toBe('Die Rechnungsnummer existiert nicht in D3 oder ist kein Zahlungsbeleg vom Typ Rechnung, Abbuchung oder Barauszahlung.');
})->with([
    'Gutschrift',
    'Barzahlung',
]);

it('akzeptiert den beleg wenn die dokument-id der rechnungsnummer entspricht', function (): void {
    fakeD3Zahlungsbeleg('T100', 'Abbuchung', 'T100', 'andere-nummer');

    expect(app(D3InvoiceValidationService::class)->getValidationError('T100'))->toBeNull();
});

it('prüft leere nummern und das format ohne d3', function (): void {
    $service = app(D3InvoiceValidationService::class);

    expect($service->getValidationError(''))->toBeNull()
        ->and($service->getValidationError('R-100'))->toBe('Das Format der Rechnungsnummer ist ungültig. Erwartet: T gefolgt von Ziffern (z. B. T12345).');
});

it('respektiert eine eingeschränkte belegtyp-liste aus der config', function (): void {
    config(['intranet-app-assets.d3_invoice_belegtypen' => 'Abbuchung']);
    fakeD3Zahlungsbeleg('T100', 'Rechnung');

    expect(app(D3InvoiceValidationService::class)->getValidationError('T100'))
        ->toBe('Die Rechnungsnummer existiert nicht in D3 oder ist kein Zahlungsbeleg vom Typ Abbuchung.');
});
