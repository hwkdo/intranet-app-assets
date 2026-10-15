<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppAssets\Livewire\Apps\Assets\ConfigmgrSerialMismatchesOverview;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Hwkdo\IntranetAppAssets\Support\AssetNavBadgeCounts;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::findOrCreate('see-app-assets', 'web');
    Permission::findOrCreate('manage-app-assets', 'web');
});

it('listet nur assets mit abweichender sccm seriennummer in liste badge und widget', function (): void {
    $admin = User::factory()->create(['active' => true]);
    $admin->givePermissionTo(['see-app-assets', 'manage-app-assets']);

    $type = AssetType::query()->create(['name' => 'mismatch-type-'.uniqid('', true)]);
    $vendor = AssetVendor::query()->create(['name' => 'mismatch-vendor-'.uniqid('', true)]);
    $suffix = uniqid('', true);

    $mismatchName = 'Abweichend-'.$suffix;
    $matchName = 'Gleich-'.$suffix;
    $placeholderName = 'Platzhalter-'.$suffix;
    $assetSerial = 'ASSET-'.$suffix;
    $sccmSerial = 'SCCM-'.$suffix;

    Asset::query()->create([
        'serial_number' => $assetSerial,
        'configmgr_serial_number' => $sccmSerial,
        'name' => $mismatchName,
        'model' => 'Mismatch-Modell',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    Asset::query()->create([
        'serial_number' => 'SAME-'.$suffix,
        'configmgr_serial_number' => 'same-'.$suffix,
        'name' => $matchName,
        'model' => 'Match-Modell',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    Asset::query()->create([
        'serial_number' => 'PLACE-'.$suffix,
        'configmgr_serial_number' => 'To be filled by O.E.M.',
        'name' => $placeholderName,
        'model' => 'Placeholder-Modell',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    expect(Route::has('apps.assets.admin.configmgr-serial-mismatches'))->toBeTrue()
        ->and(AssetNavBadgeCounts::configmgrSerialMismatchCount())->toBe(1);

    Livewire::actingAs($admin)
        ->test(ConfigmgrSerialMismatchesOverview::class)
        ->assertSee($mismatchName)
        ->assertSee($assetSerial)
        ->assertSee($sccmSerial)
        ->assertDontSee($matchName)
        ->assertDontSee($placeholderName)
        ->call('sortBy', 'serial_number')
        ->assertSet('sortColumn', 'serial_number')
        ->assertSee($mismatchName);

    Livewire::actingAs($admin)
        ->test('intranet-app-assets::apps.assets.widgets.falsche-seriennummern')
        ->assertSee('Falsche Seriennummern (1)')
        ->assertSee($mismatchName)
        ->assertSee($assetSerial)
        ->assertSee($sccmSerial)
        ->assertDontSee($matchName)
        ->assertDontSee($placeholderName);

    $this->actingAs($admin)
        ->get(route('apps.assets.admin.configmgr-serial-mismatches'))
        ->assertOk()
        ->assertSee('Falsche Seriennummern')
        ->assertSee(route('apps.assets.admin.configmgr-serial-mismatches'), false);
});

it('hebt den unterschied hervor und uebernimmt die sccm seriennummer', function (): void {
    $admin = User::factory()->create(['active' => true]);
    $admin->givePermissionTo(['see-app-assets', 'manage-app-assets']);

    $type = AssetType::query()->create(['name' => 'adopt-type-'.uniqid('', true)]);
    $vendor = AssetVendor::query()->create(['name' => 'adopt-vendor-'.uniqid('', true)]);
    $name = 'Uebernehmen-'.uniqid('', true);

    $swapped = \Hwkdo\IntranetAppAssets\Support\SerialNumberDiff::split('0B334YW25313GX', '0B334YW23513GX');
    expect(collect($swapped['asset'])->firstWhere('changed', true)['text'])->toBe('53')
        ->and(collect($swapped['sccm'])->firstWhere('changed', true)['text'])->toBe('35');

    $asset = Asset::query()->create([
        'serial_number' => 'YLT0N1V9',
        'configmgr_serial_number' => 'YLT0N1V8',
        'name' => $name,
        'model' => 'Adopt-Modell',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    Livewire::actingAs($admin)
        ->test(ConfigmgrSerialMismatchesOverview::class)
        ->assertSee('Aus SCCM übernehmen')
        ->assertSeeHtml('rgb(239 68 68 / 0.45)')
        ->call('adoptConfigmgrSerial', $asset->id)
        ->assertDontSee($name);

    expect($asset->fresh()->serial_number)->toBe('YLT0N1V8')
        ->and($asset->fresh()->configmgrSerialMismatches())->toBeFalse();
});

it('zeigt in der falsche-seriennummern-tabelle die volle seriennummer ohne kuerzung', function (): void {
    $admin = User::factory()->create(['active' => true]);
    $admin->givePermissionTo(['see-app-assets', 'manage-app-assets']);

    $type = AssetType::query()->create(['name' => 'full-sn-type-'.uniqid('', true)]);
    $vendor = AssetVendor::query()->create(['name' => 'full-sn-vendor-'.uniqid('', true)]);
    $suffix = uniqid('', true);
    $assetSerial = 'PREFIXLONG-'.$suffix.'-ASSETTAIL';
    $sccmSerial = 'PREFIXLONG-'.$suffix.'-SCCMTAIL';

    Asset::query()->create([
        'serial_number' => $assetSerial,
        'configmgr_serial_number' => $sccmSerial,
        'name' => 'Voll-SN-'.$suffix,
        'model' => 'Voll-SN-Modell',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    Livewire::actingAs($admin)
        ->test(ConfigmgrSerialMismatchesOverview::class)
        ->assertSee($assetSerial)
        ->assertSee($sccmSerial)
        ->assertSee('PREFIXLONG-'.$suffix);
});
