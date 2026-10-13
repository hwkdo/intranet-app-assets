<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetNote;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Hwkdo\IntranetAppAssets\Services\RegisterFidoAssetService;

beforeEach(function (): void {
    $this->type = AssetType::query()->create(['name' => RegisterFidoAssetService::ASSET_TYPE_NAME]);
    $this->vendor = AssetVendor::query()->create(['name' => 'Yubico']);
    $this->otherType = AssetType::query()->create(['name' => 'Laptop-'.uniqid('', true)]);
});

it('zieht legacy kommentare für yubikeys ohne pin notiz nach', function (): void {
    $author = User::factory()->create(['legacy_id' => 77]);
    $asset = yubikeyAsset(4101, 'YK-4101');
    $withPin = yubikeyAsset(4102, 'YK-4102');
    $withPin->notes()->create([
        'note' => 'FIDO device registered via API with PIN 999999',
        'user_id' => $author->id,
    ]);
    $laptop = Asset::query()->create([
        'legacy_id' => 4103,
        'serial_number' => 'LT-4103',
        'model' => 'Laptop',
        'asset_type_id' => $this->otherType->id,
        'asset_vendor_id' => $this->vendor->id,
    ]);

    mockLegacyNotes([
        legacyNote(501, 4101, 'PIN: 654321', 77, '2024-03-01 10:00:00'),
        legacyNote(502, 4101, 'ausgegeben an Empfang', 77, '2024-03-02 10:00:00'),
        legacyNote(503, 4102, 'PIN: 111111', 77),
        legacyNote(504, 4103, 'PIN: 222222', 77),
        [
            'id' => 505,
            'bemerkung' => 'Übergabe ohne PIN',
            'user_id' => 77,
            'noteable_type' => 'App\\Asset\\Uebergabe',
            'noteable_id' => 4101,
        ],
    ]);

    $this->artisan('intranet-app-assets:backfill-yubikey-notes')
        ->expectsOutputToContain('Kandidaten: 1')
        ->expectsOutputToContain('Neue Notizen: 2')
        ->expectsOutputToContain('Davon Assets mit PIN-Text: 1')
        ->expectsOutputToContain('YK-4101')
        ->doesntExpectOutputToContain('654321')
        ->assertSuccessful();

    $notes = AssetNote::query()->where('noteable_id', $asset->id)->orderBy('legacy_id')->get();
    expect($notes)->toHaveCount(2)
        ->and($notes[0]->note)->toBe('PIN: 654321')
        ->and($notes[0]->legacy_id)->toBe(501)
        ->and($notes[0]->user_id)->toBe($author->id)
        ->and($notes[0]->created_at?->format('Y-m-d H:i:s'))->toBe('2024-03-01 10:00:00')
        ->and($notes[1]->note)->toBe('ausgegeben an Empfang')
        ->and(AssetNote::query()->where('legacy_id', 503)->exists())->toBeFalse()
        ->and(AssetNote::query()->where('legacy_id', 504)->exists())->toBeFalse()
        ->and(AssetNote::query()->where('legacy_id', 505)->exists())->toBeFalse()
        ->and($laptop->fresh()->serial_number)->toBe('LT-4103')
        ->and($asset->fresh()->serial_number)->toBe('YK-4101');
});

it('schreibt im dry-run keine notizen', function (): void {
    yubikeyAsset(4201, 'YK-4201');
    mockLegacyNotes([
        legacyNote(601, 4201, 'PIN: 123123', null),
    ]);

    $this->artisan('intranet-app-assets:backfill-yubikey-notes', ['--dry-run' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->expectsOutputToContain('YK-4201')
        ->doesntExpectOutputToContain('123123')
        ->assertSuccessful();

    expect(AssetNote::query()->count())->toBe(0);
});

it('legt eine bereits importierte legacy notiz nicht erneut an', function (): void {
    $asset = yubikeyAsset(4301, 'YK-4301');
    $asset->notes()->create([
        'legacy_id' => 701,
        'note' => 'ausgegeben an Empfang',
    ]);
    mockLegacyNotes([
        legacyNote(701, 4301, 'ausgegeben an Empfang', null),
        legacyNote(702, 4301, 'PIN: 444444', null),
    ]);

    $this->artisan('intranet-app-assets:backfill-yubikey-notes')
        ->expectsOutputToContain('Neue Notizen: 1')
        ->expectsOutputToContain('Bereits vorhandene Legacy-Notizen: 1')
        ->assertSuccessful();

    expect(AssetNote::query()->where('legacy_id', 701)->count())->toBe(1)
        ->and(AssetNote::query()->where('legacy_id', 702)->count())->toBe(1);
});

it('überspringt gelöschte yubikeys ohne with-trashed', function (): void {
    $asset = yubikeyAsset(4401, 'YK-4401');
    $asset->delete();
    mockLegacyNotes([
        legacyNote(801, 4401, 'PIN: 555555', null),
    ]);

    $this->artisan('intranet-app-assets:backfill-yubikey-notes')
        ->expectsOutputToContain('Kandidaten: 0')
        ->assertSuccessful();

    expect(AssetNote::query()->count())->toBe(0);

    $this->artisan('intranet-app-assets:backfill-yubikey-notes', ['--with-trashed' => true])
        ->expectsOutputToContain('Kandidaten: 1')
        ->assertSuccessful();

    expect(AssetNote::query()->where('legacy_id', 801)->count())->toBe(1);
});

it('bricht ab wenn die legacy api asset ids ignoriert', function (): void {
    yubikeyAsset(4501, 'YK-4501');
    $unfiltered = [];
    for ($id = 1; $id <= 1000; $id++) {
        $unfiltered[] = legacyNote($id, 900000 + $id, 'Kommentar '.$id, null);
    }
    mockLegacyNotes($unfiltered);

    $this->artisan('intranet-app-assets:backfill-yubikey-notes')
        ->expectsOutputToContain('asset_ids ignoriert')
        ->assertFailed();

    expect(AssetNote::query()->count())->toBe(0);
});

function yubikeyAsset(int $legacyId, string $serial): Asset
{
    return Asset::query()->create([
        'legacy_id' => $legacyId,
        'serial_number' => $serial,
        'model' => 'Yubikey 5 NFC',
        'asset_type_id' => test()->type->id,
        'asset_vendor_id' => test()->vendor->id,
    ]);
}

/**
 * @return array<string, mixed>
 */
function legacyNote(int $id, int $assetId, string $text, ?int $userId, ?string $createdAt = null): array
{
    return [
        'id' => $id,
        'bemerkung' => $text,
        'user_id' => $userId,
        'created_at' => $createdAt,
        'noteable_type' => 'App\\Asset\\Asset',
        'noteable_id' => $assetId,
    ];
}

/**
 * @param  list<array<string, mixed>>  $notes
 */
function mockLegacyNotes(array $notes): void
{
    $legacy = Mockery::mock(IntranetLegacyService::class);
    $legacy->shouldReceive('getAssetNotesExportForAssetIds')->andReturn($notes);
    app()->instance(IntranetLegacyService::class, $legacy);
}
