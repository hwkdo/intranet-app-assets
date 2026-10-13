<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\ConfigmgrLaravel\ConfigmgrLaravel;
use Hwkdo\IntranetAppAssets\Livewire\Apps\Assets\ConfigmgrData;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Hwkdo\IntranetAppAssets\Search\AssetSearch;
use Hwkdo\IntranetAppAssets\Search\AssetsSearchSource;
use Hwkdo\IntranetAppAssets\Support\ConfigmgrSerialNumber;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::findOrCreate('see-app-assets', 'web');
    Permission::findOrCreate('manage-app-assets', 'web');
});

it('erkennt seriennummern abweichungen ohne platzhalter und ohne satzzeichen', function (): void {
    expect(ConfigmgrSerialNumber::mismatches('PF-38BTHP', 'pf38bthp'))->toBeFalse()
        ->and(ConfigmgrSerialNumber::mismatches('YLT0N1V9', 'YLT0N1V8'))->toBeTrue()
        ->and(ConfigmgrSerialNumber::mismatches('KSMM99YW400075', 'KSMM99YW400075Y'))->toBeTrue()
        ->and(ConfigmgrSerialNumber::mismatches('YLT0N1V9', 'To be filled by O.E.M.'))->toBeFalse()
        ->and(ConfigmgrSerialNumber::mismatches('Q220400002437', 'GXXXX437XXXXX'))->toBeFalse()
        ->and(ConfigmgrSerialNumber::mismatches('YLT0N1V9', null))->toBeFalse()
        ->and(ConfigmgrSerialNumber::mismatches('', 'YLT0N1V8'))->toBeFalse()
        ->and(ConfigmgrSerialNumber::normalize(' pf-38 bthp '))->toBe('PF38BTHP');
});

it('schreibt die bios seriennummer nur bei exaktem computernamen', function (): void {
    $configmgr = Mockery::mock(ConfigmgrLaravel::class);
    $configmgr->shouldReceive('getBiosSerialNumbersByComputerName')->once()->andReturn([
        'PC-ONE' => 'BIOS-1',
    ]);
    $configmgr->shouldReceive('getSystemDataByComputerName')->andReturnUsing(function (string $name): array {
        if (strtoupper($name) === 'PC-NONE') {
            return [];
        }

        return [(object) [
            'rechnername' => $name,
            'smbios_guid' => 'guid-'.$name,
            'mac_adresse' => 'AA:BB',
            'last_logon_user' => 'user',
        ]];
    });
    app()->instance(ConfigmgrLaravel::class, $configmgr);

    $type = AssetType::query()->create([
        'name' => 'domain-'.uniqid('', true),
        'is_domain_object' => true,
    ]);
    $otherType = AssetType::query()->create([
        'name' => 'other-'.uniqid('', true),
        'is_domain_object' => false,
    ]);
    $vendor = AssetVendor::query()->create(['name' => 'vendor-'.uniqid('', true)]);

    $matched = Asset::query()->create([
        'serial_number' => 'ASSET-1',
        'model' => 'Laptop',
        'name' => 'pc-one',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);
    $cleared = Asset::query()->create([
        'serial_number' => 'ASSET-2',
        'model' => 'Laptop',
        'name' => 'PC-NONE',
        'configmgr_serial_number' => 'OLD-SERIAL',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);
    $fuzzy = Asset::query()->create([
        'serial_number' => 'ASSET-3',
        'model' => 'Laptop',
        'name' => 'PC',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);
    $other = Asset::query()->create([
        'serial_number' => 'ASSET-4',
        'model' => 'Monitor',
        'name' => 'pc-one',
        'configmgr_serial_number' => 'KEEP',
        'asset_type_id' => $otherType->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    $this->artisan('intranet-app-assets:sync-configmgr-data')->assertSuccessful();

    expect($matched->fresh()->configmgr_serial_number)->toBe('BIOS-1')
        ->and($matched->fresh()->configmgrSerialMismatches())->toBeTrue()
        ->and($matched->fresh()->toSearchableArray()['status_tokens'])->toContain('sccm_seriennummer_abweichung')
        ->and($cleared->fresh()->configmgr_serial_number)->toBeNull()
        ->and($fuzzy->fresh()->configmgr_serial_number)->toBeNull()
        ->and($other->fresh()->configmgr_serial_number)->toBe('KEEP');
});

it('bricht den sync ab wenn die bios abfrage fehlschlaegt', function (): void {
    $configmgr = Mockery::mock(ConfigmgrLaravel::class);
    $configmgr->shouldReceive('getBiosSerialNumbersByComputerName')->once()->andThrow(new RuntimeException('sccm down'));
    $configmgr->shouldReceive('getSystemDataByComputerName')->never();
    app()->instance(ConfigmgrLaravel::class, $configmgr);

    $type = AssetType::query()->create([
        'name' => 'domain-'.uniqid('', true),
        'is_domain_object' => true,
    ]);
    $vendor = AssetVendor::query()->create(['name' => 'vendor-'.uniqid('', true)]);
    $asset = Asset::query()->create([
        'serial_number' => 'ASSET-1',
        'model' => 'Laptop',
        'name' => 'PC-ONE',
        'configmgr_serial_number' => 'BLEIBT',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    $this->artisan('intranet-app-assets:sync-configmgr-data')->assertFailed();

    expect($asset->fresh()->configmgr_serial_number)->toBe('BLEIBT');
});

it('zeigt die abweichung in listen export und suche', function (): void {
    $admin = User::factory()->create(['active' => true]);
    $admin->givePermissionTo(['see-app-assets', 'manage-app-assets']);

    $type = AssetType::query()->create([
        'name' => 'domain-'.uniqid('', true),
        'is_domain_object' => true,
    ]);
    $vendor = AssetVendor::query()->create(['name' => 'vendor-'.uniqid('', true)]);
    $token = 'CFG-SN-'.uniqid('', true);

    $asset = Asset::query()->create([
        'serial_number' => 'YLT0N1V9',
        'configmgr_serial_number' => 'YLT0N1V8',
        'model' => 'Laptop',
        'name' => $token,
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
        'user_id' => $admin->id,
    ]);

    Livewire::actingAs($admin)
        ->test('intranet-app-assets::apps.assets.liste')
        ->assertSee('SN weicht ab');

    Livewire::actingAs($admin)
        ->test('intranet-app-assets::apps.assets.domaenengeraete')
        ->assertSee('SN weicht ab');

    Livewire::actingAs($admin)
        ->test('intranet-app-assets::apps.assets.search')
        ->set('searchQuery', $token)
        ->assertSee('SN weicht ab')
        ->assertSee('SCCM-Seriennummer')
        ->assertSee('YLT0N1V8');

    $listeStatus = collect(Livewire::actingAs($admin)->test('intranet-app-assets::apps.assets.liste')->instance()->getExportColumns())
        ->firstWhere('heading', 'Status');
    $domainStatus = collect(Livewire::actingAs($admin)->test('intranet-app-assets::apps.assets.domaenengeraete')->instance()->getExportColumns())
        ->firstWhere('heading', 'Status');

    expect($listeStatus['value']($asset->fresh()))->toBe('SN weicht ab')
        ->and($domainStatus['value']($asset->fresh()))->toBe('SN weicht ab');

    Livewire::test(ConfigmgrData::class, [
        'computerName' => '',
        'serialNumber' => 'YLT0N1V9',
        'configmgrSerialNumber' => 'YLT0N1V8',
    ])->assertSee('Seriennummer weicht ab')
        ->assertSee('YLT0N1V8')
        ->assertSee('Seriennummer (BIOS)');

    $results = app(AssetsSearchSource::class)->search($token, $admin, 10);

    expect($results)->toHaveCount(1)
        ->and($results->first()->subtitle)->toContain('SCCM-Seriennummer weicht ab');

    expect(AssetSearch::query('sccm_seriennummer_abweichung', $admin, 20)->pluck('id')->all())
        ->toContain($asset->id);
});
