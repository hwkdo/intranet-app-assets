<?php

use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppAssets\Services\LegacyAssetImportService;

it('returns legacy assets fetched from legacy service', function () {
    $legacyService = \Mockery::mock(IntranetLegacyService::class);
    $legacyService->shouldReceive('getAssetsExportAlle')
        ->once()
        ->andReturn([
            ['id' => 1, 'itexiaid' => 'ITX-1'],
            ['id' => 2, 'itexiaid' => 'ITX-2'],
        ]);

    $service = new LegacyAssetImportService();
    $result = $service->fetchLegacyAssets($legacyService);

    expect($result)->toHaveCount(2)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[1]['itexiaid'])->toBe('ITX-2');
});

it('returns zero counters when no legacy ids are selected for import', function () {
    $legacyService = \Mockery::mock(IntranetLegacyService::class);
    $service = new LegacyAssetImportService();

    $result = $service->importMissingByLegacyIds($legacyService, [], []);

    expect($result)->toBe([
        'imported' => 0,
        'skipped' => 0,
        'selected' => 0,
    ]);
});
