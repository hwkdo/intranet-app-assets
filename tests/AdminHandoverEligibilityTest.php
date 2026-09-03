<?php

declare(strict_types=1);

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Support\AdminHandoverEligibility;

it('ist eligible bei auf lager', function (): void {
    $asset = new Asset([
        'user_id' => null,
        'is_in_stock' => true,
        'is_missing' => false,
    ]);

    expect(AdminHandoverEligibility::isEligible($asset))->toBeTrue();
});

it('ist nicht eligible bei gemeinschaftsgeraet', function (): void {
    $asset = new Asset([
        'user_id' => null,
        'is_in_stock' => false,
        'is_missing' => false,
    ]);

    expect(AdminHandoverEligibility::isEligible($asset))->toBeFalse();
});

it('ist nicht eligible wenn vermisst', function (): void {
    $asset = new Asset([
        'user_id' => null,
        'is_in_stock' => true,
        'is_missing' => true,
    ]);

    expect(AdminHandoverEligibility::isEligible($asset))->toBeFalse();
});
