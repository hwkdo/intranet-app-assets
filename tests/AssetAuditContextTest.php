<?php

use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;

it('provides current source inside context', function () {
    $seen = AssetAuditContext::runWith('assets.edit.save', function () {
        return AssetAuditContext::source();
    });

    expect($seen)->toBe('assets.edit.save');
});

it('restores previous source for nested contexts', function () {
    $seen = AssetAuditContext::runWith('outer', function () {
        $inner = AssetAuditContext::runWith('inner', function () {
            return AssetAuditContext::source();
        });

        return [$inner, AssetAuditContext::source()];
    });

    expect($seen)->toBe(['inner', 'outer'])
        ->and(AssetAuditContext::source())->toBeNull();
});
