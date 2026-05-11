<?php

use Hwkdo\IntranetAppAssets\Services\LegacyOrderNumberValidationService;
use Hwkdo\IntranetAppAssets\Services\LocalOrderNumberValidationService;

beforeEach(function () {
    $this->service = new LocalOrderNumberValidationService();
});

it('returns null for empty string', function () {
    expect($this->service->getValidationError(''))->toBeNull();
    expect($this->service->getValidationError('   '))->toBeNull();
});

it('returns format error for number that is too short', function () {
    $error = $this->service->getValidationError('12345');
    expect($error)->toContain('Ungültiges Format');
});

it('returns format error for number with letters', function () {
    $error = $this->service->getValidationError('ABCDEFGHI');
    expect($error)->toContain('Ungültiges Format');
});

it('returns existence error when bestellungen class does not exist', function () {
    if (class_exists('Hwkdo\IntranetAppBestellungen\Models\Bestellung')) {
        $this->markTestSkipped('Bestellungen-Package ist geladen – Test nicht anwendbar ohne Alias-Mock.');
    }

    // Jede 9-stellige Nummer die dem Format entspricht schlägt fehl da Klasse nicht existiert
    $number = LegacyOrderNumberValidationService::isValidFormat('112345678') ? '112345678' : '312345678';
    $error = $this->service->getValidationError($number);
    expect($error)->toContain('existiert nicht');
});
