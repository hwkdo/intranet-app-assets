<?php

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Services\AssetItexiaRoomSearchHintResolver;

it('priorisiert den raum des besitzers gegenueber asset location', function () {
    $owner = new User(['raum' => '  IT-Raum A  ']);
    $asset = new Asset(['location' => 'Hamburg']);
    $asset->setRelation('owner', $owner);

    $r = AssetItexiaRoomSearchHintResolver::resolve($asset);

    expect($r['source'])->toBe(AssetItexiaRoomSearchHintResolver::SOURCE_OWNER_RAUM)
        ->and($r['hint'])->toBe('IT-Raum A');
});

it('nutzt asset location wenn besitzer keinen raum hat', function () {
    $owner = new User(['raum' => null]);
    $asset = new Asset(['location' => '  Standort X  ']);
    $asset->setRelation('owner', $owner);

    $r = AssetItexiaRoomSearchHintResolver::resolve($asset);

    expect($r['source'])->toBe(AssetItexiaRoomSearchHintResolver::SOURCE_ASSET_LOCATION)
        ->and($r['hint'])->toBe('Standort X');
});

it('nutzt asset location wenn kein besitzer gesetzt ist', function () {
    $asset = new Asset(['location' => 'Lager']);
    $asset->setRelation('owner', null);

    $r = AssetItexiaRoomSearchHintResolver::resolve($asset);

    expect($r['source'])->toBe(AssetItexiaRoomSearchHintResolver::SOURCE_ASSET_LOCATION)
        ->and($r['hint'])->toBe('Lager');
});

it('liefert none wenn weder besitzer-raum noch location gesetzt sind', function () {
    $owner = new User(['raum' => '   ']);
    $asset = new Asset(['location' => null]);
    $asset->setRelation('owner', $owner);

    $r = AssetItexiaRoomSearchHintResolver::resolve($asset);

    expect($r['source'])->toBe(AssetItexiaRoomSearchHintResolver::SOURCE_NONE)
        ->and($r['hint'])->toBeNull();
});
