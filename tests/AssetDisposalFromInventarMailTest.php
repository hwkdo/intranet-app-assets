<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppAssets\Mail\AssetDeletedInItexiaInventoryMail;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Hwkdo\IntranetAppAssets\Services\AssetDisposalFromInventarService;
use Hwkdo\IntranetAppAssets\Services\AssetItexiaDeleteInventoryNotifier;
use Hwkdo\SeventhingsLaravel\Models\Asset as ItexiaAsset;
use Hwkdo\SeventhingsLaravel\SeventhingsLaravel;
use Illuminate\Support\Facades\Mail;

function createAssetWithItexiaId(string $itexiaId): Asset
{
    $type = AssetType::query()->create(['name' => 'test-type-'.uniqid('', true)]);
    $vendor = AssetVendor::query()->create(['name' => 'test-vendor-'.uniqid('', true)]);

    return Asset::query()->create([
        'serial_number' => 'SN-'.uniqid('', true),
        'model' => 'Testmodell',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
        'itexia_id' => $itexiaId,
        'itexia_uuid' => 'uuid-'.uniqid('', true),
    ]);
}

it('sendet keine asset inventar mail bei anlagenabgang aus der inventar app', function (): void {
    Mail::fake();

    $actor = User::factory()->create();
    $asset = createAssetWithItexiaId('12345');

    app(AssetDisposalFromInventarService::class)->disposeFromInventarMeldung(
        $asset,
        $actor,
        'Notiz aus Inventar',
        'Inventar-Anlagenabgang',
        [
            'type_name' => 'Laptop',
            'vendor_name' => 'Dell',
            'model' => 'XPS',
            'itexia_id' => '12345',
            'itexia_uuid' => $asset->itexia_uuid,
            'display_name' => $asset->display_name,
        ],
    );

    Mail::assertNothingQueued();
    expect($asset->fresh()->trashed())->toBeTrue();
});

it('sendet asset inventar mail bei direktem loeschen mit itexia verknuepfung', function (): void {
    Mail::fake();

    $actor = User::factory()->create();
    $asset = createAssetWithItexiaId('67890');
    $snapshot = [
        'type_name' => 'Laptop',
        'vendor_name' => 'Dell',
        'model' => 'XPS',
        'itexia_id' => '67890',
        'itexia_uuid' => $asset->itexia_uuid,
        'display_name' => $asset->display_name,
    ];

    $row = (object) ['barcode' => '67890', 'asset_uuid' => $asset->itexia_uuid, 'inventory_name' => 'Test'];
    $itexiaAsset = new ItexiaAsset($row);

    mock(SeventhingsLaravel::class)
        ->shouldReceive('findAsset')
        ->once()
        ->with('67890')
        ->andReturn($itexiaAsset);

    $asset->delete();

    app(AssetItexiaDeleteInventoryNotifier::class)->notifyAfterSoftDelete(
        (int) $asset->id,
        'Direkt gelöscht',
        (int) $actor->id,
        $snapshot,
    );

    Mail::assertQueued(AssetDeletedInItexiaInventoryMail::class, 1);
});
