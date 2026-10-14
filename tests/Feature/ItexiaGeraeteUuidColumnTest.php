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

it('zeigt nur den ersten uuid block und findet die volle uuid über die suche', function (): void {
    $admin = User::factory()->create(['active' => true]);
    $admin->givePermissionTo(['see-app-assets', 'manage-app-assets']);

    $type = AssetType::query()->create(['name' => 'itexia-type-'.uniqid('', true)]);
    $vendor = AssetVendor::query()->create(['name' => 'itexia-vendor-'.uniqid('', true)]);
    $suffix = uniqid('', true);
    $uuid = 'a1b2c3d4-e29b-41d4-a716-44665544'.$suffix;
    $name = 'Itexia-Geraet-'.$suffix;
    $otherName = 'Anderes-Geraet-'.$suffix;

    Asset::query()->create([
        'serial_number' => 'SN-UUID-'.$suffix,
        'name' => $name,
        'model' => 'Modell',
        'itexia_id' => 'ITX-'.$suffix,
        'itexia_uuid' => $uuid,
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);
    Asset::query()->create([
        'serial_number' => 'SN-OTHER-'.$suffix,
        'name' => $otherName,
        'model' => 'Modell',
        'itexia_id' => 'ITX-OTHER-'.$suffix,
        'itexia_uuid' => 'ffffffff-1111-2222-3333-44444444'.$suffix,
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    Livewire::actingAs($admin)
        ->test('intranet-app-assets::apps.assets.itexiageraete')
        ->set('search', 'a716-44665544'.$suffix)
        ->assertSee($name)
        ->assertDontSee($otherName)
        ->assertSeeHtml('>a1b2c3d4<')
        ->assertSee($uuid)
        ->assertDontSeeHtml('>'.$uuid.'<');
});
