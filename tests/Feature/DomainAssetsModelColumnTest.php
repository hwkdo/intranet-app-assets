<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::findOrCreate('see-app-assets', 'web');
    Permission::findOrCreate('manage-app-assets', 'web');
});

it('blendet die modell-spalte auf domaenengeraeten aus und filtert das modell trotzdem', function (): void {
    $admin = User::factory()->create(['active' => true]);
    $admin->givePermissionTo(['see-app-assets', 'manage-app-assets']);

    $type = AssetType::query()->create([
        'name' => 'domain-'.uniqid('', true),
        'is_domain_object' => true,
    ]);
    $vendor = AssetVendor::query()->create(['name' => 'vendor-'.uniqid('', true)]);
    $model = 'Nur-Domaenen-Modell-'.uniqid('', true);
    $name = 'Domaenenname-'.uniqid('', true);
    $otherName = 'Anderer-Domaenenname-'.uniqid('', true);

    Asset::query()->create([
        'serial_number' => 'SN-'.uniqid('', true),
        'name' => $name,
        'model' => $model,
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    Asset::query()->create([
        'serial_number' => 'SN-'.uniqid('', true),
        'name' => $otherName,
        'model' => 'Anderes-Domaenen-Modell-'.uniqid('', true),
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    Livewire::actingAs($admin)
        ->test('intranet-app-assets::apps.assets.domaenengeraete')
        ->assertSet('showModel', false)
        ->assertSee('Modell anzeigen')
        ->assertSee($name)
        ->assertDontSee($model)
        ->set('showModel', true)
        ->assertSee($model)
        ->set('showModel', false)
        ->set('search', $model)
        ->assertSee($name)
        ->assertDontSee($otherName)
        ->assertDontSee($model);
});
