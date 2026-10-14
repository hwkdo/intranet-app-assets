<?php

declare(strict_types=1);

use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;

beforeEach(function (): void {
    config([
        'legacy.base_api_url' => 'https://legacy.test/api/',
        'legacy.base_api_token' => 'token',
    ]);

    $this->type = AssetType::query()->create(['name' => 'Laptop-'.uniqid('', true)]);
    $this->vendor = AssetVendor::query()->create(['name' => 'Vendor-'.uniqid('', true)]);
});

it('überschreibt created_at mit dem originalen legacy zeitpunkt und lässt updated_at stehen', function (): void {
    $imported = legacyCreatedAtAsset(7101, 'SN-7101', '2026-10-01 12:00:00', '2026-10-02 08:00:00');
    $alreadyCorrect = legacyCreatedAtAsset(7102, 'SN-7102', '2018-03-04 09:30:00', '2026-10-02 08:00:00');
    $native = legacyCreatedAtAsset(null, 'SN-NATIVE', '2026-09-01 10:00:00', '2026-09-01 10:00:00');
    $trashed = legacyCreatedAtAsset(7103, 'SN-7103', '2026-10-01 12:00:00', '2026-10-02 08:00:00');
    $trashed->delete();

    mockLegacyCreatedAtExport([
        legacyCreatedAtRow(7101, '2019-04-02T06:15:00.000000Z'),
        legacyCreatedAtRow(7102, '2018-03-04 09:30:00'),
        legacyCreatedAtRow(7103, '2017-01-15T10:00:00+01:00'),
        legacyCreatedAtRow(7999, '2016-01-01 00:00:00'),
    ]);

    $this->artisan('intranet-app-assets:backfill-legacy-created-at')
        ->expectsOutputToContain('Überschrieben: 2')
        ->expectsOutputToContain('Bereits korrekt: 1')
        ->expectsOutputToContain('Ohne Treffer im Export: 0')
        ->expectsOutputToContain('SN-7101')
        ->assertSuccessful();

    $imported->refresh();
    $alreadyCorrect->refresh();
    $native->refresh();
    $trashed = Asset::withTrashed()->findOrFail($trashed->id);

    expect($imported->created_at?->format('Y-m-d H:i:s'))->toBe('2019-04-02 08:15:00')
        ->and($imported->updated_at?->format('Y-m-d H:i:s'))->toBe('2026-10-02 08:00:00')
        ->and($alreadyCorrect->created_at?->format('Y-m-d H:i:s'))->toBe('2018-03-04 09:30:00')
        ->and($alreadyCorrect->updated_at?->format('Y-m-d H:i:s'))->toBe('2026-10-02 08:00:00')
        ->and($native->created_at?->format('Y-m-d H:i:s'))->toBe('2026-09-01 10:00:00')
        ->and($trashed->created_at?->format('Y-m-d H:i:s'))->toBe('2017-01-15 10:00:00')
        ->and($trashed->trashed())->toBeTrue()
        ->and(AssetHistory::query()->count())->toBe(0);
});

it('schreibt im dry-run kein created_at', function (): void {
    $asset = legacyCreatedAtAsset(7201, 'SN-7201', '2026-10-01 12:00:00', '2026-10-01 12:00:00');
    mockLegacyCreatedAtExport([
        legacyCreatedAtRow(7201, '2015-06-01 07:00:00'),
    ]);

    $this->artisan('intranet-app-assets:backfill-legacy-created-at', ['--dry-run' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->expectsOutputToContain('Würde überschreiben: 1')
        ->assertSuccessful();

    expect($asset->fresh()->created_at?->format('Y-m-d H:i:s'))->toBe('2026-10-01 12:00:00');
});

it('überspringt assets ohne gültiges legacy created_at', function (): void {
    $asset = legacyCreatedAtAsset(7301, 'SN-7301', '2026-10-01 12:00:00', '2026-10-01 12:00:00');
    $missing = legacyCreatedAtAsset(7302, 'SN-7302', '2026-10-01 12:00:00', '2026-10-01 12:00:00');
    mockLegacyCreatedAtExport([
        legacyCreatedAtRow(7301, '0000-00-00 00:00:00'),
        ['id' => 7302, 'created_at' => null],
    ]);

    $this->artisan('intranet-app-assets:backfill-legacy-created-at')
        ->expectsOutputToContain('Überschrieben: 0')
        ->expectsOutputToContain('Ohne gültiges created_at: 2')
        ->assertSuccessful();

    expect($asset->fresh()->created_at?->format('Y-m-d H:i:s'))->toBe('2026-10-01 12:00:00')
        ->and($missing->fresh()->created_at?->format('Y-m-d H:i:s'))->toBe('2026-10-01 12:00:00');
});

it('bricht ab wenn der legacy export leer ist', function (): void {
    $asset = legacyCreatedAtAsset(7401, 'SN-7401', '2026-10-01 12:00:00', '2026-10-01 12:00:00');
    mockLegacyCreatedAtExport([]);

    $this->artisan('intranet-app-assets:backfill-legacy-created-at')
        ->expectsOutputToContain('keine Assets geliefert')
        ->assertFailed();

    expect($asset->fresh()->created_at?->format('Y-m-d H:i:s'))->toBe('2026-10-01 12:00:00');
});

it('bricht ab wenn die legacy api nicht konfiguriert ist', function (): void {
    config([
        'legacy.base_api_url' => null,
        'legacy.base_api_token' => null,
    ]);

    $this->artisan('intranet-app-assets:backfill-legacy-created-at')
        ->expectsOutputToContain('Legacy-API nicht konfiguriert')
        ->assertFailed();
});

function legacyCreatedAtAsset(?int $legacyId, string $serial, string $createdAt, string $updatedAt): Asset
{
    $asset = Asset::query()->create([
        'legacy_id' => $legacyId,
        'serial_number' => $serial,
        'model' => 'Test',
        'asset_type_id' => test()->type->id,
        'asset_vendor_id' => test()->vendor->id,
    ]);

    Asset::withoutTimestamps(function () use ($asset, $createdAt, $updatedAt): void {
        $asset->created_at = $createdAt;
        $asset->updated_at = $updatedAt;
        $asset->save();
    });

    return $asset->fresh();
}

/**
 * @return array<string, mixed>
 */
function legacyCreatedAtRow(int $id, ?string $createdAt): array
{
    return [
        'id' => $id,
        'created_at' => $createdAt,
    ];
}

/**
 * @param  list<array<string, mixed>>  $assets
 */
function mockLegacyCreatedAtExport(array $assets): void
{
    $legacy = Mockery::mock(IntranetLegacyService::class);
    $legacy->shouldReceive('getAssetsExportAlle')->once()->with('all')->andReturn($assets);
    app()->instance(IntranetLegacyService::class, $legacy);
}
