<?php

declare(strict_types=1);

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\AssetListeAdminActions;

it('liefert klaerungs- und vermisst-links aus asset-flags', function (): void {
    $asset = new Asset([
        'is_clarification' => true,
        'is_missing' => true,
    ]);
    $asset->id = 42;

    $actions = AssetListeAdminActions::resolveLinks($asset);

    expect($actions)->toHaveCount(2)
        ->and(collect($actions)->pluck('key')->all())->toBe(['clarification', 'missing'])
        ->and($actions[0]['href'])->toBe(route('apps.assets.admin.clarification.resolve', $asset))
        ->and($actions[1]['href'])->toBe(route('apps.assets.admin.missing.resolve', $asset));
});

it('liefert rueckgabe- und uebergabe-links aus related models', function (): void {
    $asset = new Asset([
        'is_clarification' => false,
        'is_missing' => false,
    ]);
    $asset->id = 7;

    $pendingReturn = new AssetReturn;
    $pendingReturn->id = 11;

    $openHandover = new Handover;
    $openHandover->id = 22;

    $rejectedHandover = new Handover;
    $rejectedHandover->id = 33;

    $actions = AssetListeAdminActions::resolveLinks($asset, [
        'pending_return' => $pendingReturn,
        'open_handover' => $openHandover,
        'rejected_handover' => $rejectedHandover,
    ]);

    expect(collect($actions)->pluck('key')->all())->toBe([
        'pending_return',
        'open_handover',
        'rejected_handover',
    ])
        ->and($actions[0]['href'])->toBe(route('apps.assets.admin.return.complete', $pendingReturn))
        ->and($actions[1]['href'])->toBe(route('apps.assets.admin.open-handover.resolve', $openHandover))
        ->and($actions[2]['href'])->toBe(route('apps.assets.admin.rejected-handover.resolve', $rejectedHandover));
});

it('liefert keine resolve-links ohne spezialzustand', function (): void {
    $asset = new Asset([
        'is_clarification' => false,
        'is_missing' => false,
    ]);
    $asset->id = 1;

    expect(AssetListeAdminActions::resolveLinks($asset))->toBe([]);
});
