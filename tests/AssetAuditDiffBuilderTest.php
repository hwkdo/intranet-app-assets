<?php

use Hwkdo\IntranetAppAssets\Support\AssetAuditDiffBuilder;

it('builds old and new value diffs for allowed fields', function () {
    $diff = AssetAuditDiffBuilder::build(
        original: [
            'model' => 'ThinkPad X1',
            'user_id' => 5,
            'is_missing' => false,
            'updated_at' => 'ignore-me',
        ],
        changes: [
            'model' => 'ThinkPad X1 Gen 12',
            'user_id' => 8,
            'is_missing' => true,
            'updated_at' => 'will-be-ignored-by-allowed-list',
        ],
        allowedFields: ['model', 'user_id', 'is_missing']
    );

    expect($diff)->toBe([
        'model' => ['old' => 'ThinkPad X1', 'new' => 'ThinkPad X1 Gen 12'],
        'user_id' => ['old' => 5, 'new' => 8],
        'is_missing' => ['old' => false, 'new' => true],
    ]);
});

it('ignores unchanged or non allowed fields', function () {
    $diff = AssetAuditDiffBuilder::build(
        original: [
            'name' => 'Asset A',
            'location' => 'Büro',
        ],
        changes: [
            'name' => 'Asset A',
            'location' => 'Lager',
        ],
        allowedFields: ['name']
    );

    expect($diff)->toBe([]);
});
