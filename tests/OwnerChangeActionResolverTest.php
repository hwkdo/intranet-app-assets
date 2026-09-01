<?php

use Hwkdo\IntranetAppAssets\Support\OwnerChangeActionResolver;

it('priorisiert offene rueckgabe vor allen anderen faellen', function () {
    $result = OwnerChangeActionResolver::resolve([
        'has_pending_return' => true,
        'has_open_handover' => true,
        'has_rejected_handover' => true,
        'is_clarification' => true,
        'pending_return_href' => '/returns/1',
        'open_handover_href' => '/open/1',
        'rejected_handover_href' => '/rejected/1',
        'clarification_href' => '/clarification/1',
    ]);

    expect($result)->not()->toBeNull()
        ->and($result['label'])->toBe('Offene Rückgabe bearbeiten')
        ->and($result['href'])->toBe('/returns/1');
});

it('liefert offene uebergabe wenn keine rueckgabe offen ist', function () {
    $result = OwnerChangeActionResolver::resolve([
        'has_pending_return' => false,
        'has_open_handover' => true,
        'has_rejected_handover' => true,
        'is_clarification' => true,
        'pending_return_href' => null,
        'open_handover_href' => '/open/2',
        'rejected_handover_href' => '/rejected/2',
        'clarification_href' => '/clarification/2',
    ]);

    expect($result)->not()->toBeNull()
        ->and($result['label'])->toBe('Offene Übergabe bearbeiten')
        ->and($result['href'])->toBe('/open/2');
});

it('liefert abgelehnte uebergabe vor klaerungsfall', function () {
    $result = OwnerChangeActionResolver::resolve([
        'has_pending_return' => false,
        'has_open_handover' => false,
        'has_rejected_handover' => true,
        'is_clarification' => true,
        'pending_return_href' => null,
        'open_handover_href' => null,
        'rejected_handover_href' => '/rejected/3',
        'clarification_href' => '/clarification/3',
    ]);

    expect($result)->not()->toBeNull()
        ->and($result['label'])->toBe('Abgelehnte Übergabe bearbeiten')
        ->and($result['href'])->toBe('/rejected/3');
});

it('liefert klaerungsfall wenn sonst nichts offen ist', function () {
    $result = OwnerChangeActionResolver::resolve([
        'has_pending_return' => false,
        'has_open_handover' => false,
        'has_rejected_handover' => false,
        'is_clarification' => true,
        'is_missing' => true,
        'pending_return_href' => null,
        'open_handover_href' => null,
        'rejected_handover_href' => null,
        'clarification_href' => '/clarification/4',
        'missing_href' => '/missing/4',
    ]);

    expect($result)->not()->toBeNull()
        ->and($result['label'])->toBe('Klärungsfall bearbeiten')
        ->and($result['href'])->toBe('/clarification/4');
});

it('liefert vermisst-fall vor null wenn sonst nichts offen ist', function () {
    $result = OwnerChangeActionResolver::resolve([
        'has_pending_return' => false,
        'has_open_handover' => false,
        'has_rejected_handover' => false,
        'is_clarification' => false,
        'is_missing' => true,
        'pending_return_href' => null,
        'open_handover_href' => null,
        'rejected_handover_href' => null,
        'clarification_href' => null,
        'missing_href' => '/missing/5',
    ]);

    expect($result)->not()->toBeNull()
        ->and($result['label'])->toBe('Vermisst-Fall bearbeiten')
        ->and($result['href'])->toBe('/missing/5');
});

it('liefert null wenn kein spezialfall vorliegt', function () {
    $result = OwnerChangeActionResolver::resolve([
        'has_pending_return' => false,
        'has_open_handover' => false,
        'has_rejected_handover' => false,
        'is_clarification' => false,
        'is_missing' => false,
        'pending_return_href' => null,
        'open_handover_href' => null,
        'rejected_handover_href' => null,
        'clarification_href' => null,
        'missing_href' => null,
    ]);

    expect($result)->toBeNull();
});
