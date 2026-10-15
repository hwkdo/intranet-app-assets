<?php

declare(strict_types=1);

use App\Models\User;
use Flux\DateRange;
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

it('beschriftet die filter-defaults mit der jeweiligen dimension', function (): void {
    $admin = User::factory()->create(['active' => true]);
    $admin->givePermissionTo(['see-app-assets', 'manage-app-assets']);

    Livewire::actingAs($admin)
        ->test('intranet-app-assets::apps.assets.itexiageraete')
        ->assertSee('Typen filtern…')
        ->assertSee('Itexia-Status: Alle')
        ->assertSee('Rechnung/Bestellung: Alle')
        ->assertSee('Itexia-Raum: Alle')
        ->assertSee('Angelegt…')
        ->assertDontSee('Alle Itexia-Geräte');
});

it('filtert itexia-geräte nach created_at zeitraum', function (): void {
    $admin = User::factory()->create(['active' => true]);
    $admin->givePermissionTo(['see-app-assets', 'manage-app-assets']);

    $type = AssetType::query()->create(['name' => 'itexia-type-'.uniqid('', true)]);
    $vendor = AssetVendor::query()->create(['name' => 'itexia-vendor-'.uniqid('', true)]);
    $suffix = uniqid('', true);

    $inside = Asset::query()->create([
        'serial_number' => 'SN-IN-'.$suffix,
        'name' => 'Innerhalb-'.$suffix,
        'model' => 'Modell',
        'itexia_id' => 'ITX-IN-'.$suffix,
        'itexia_uuid' => 'aaaaaaaa-1111-2222-3333-44444444'.$suffix,
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);
    $inside->created_at = '2024-06-15 12:00:00';
    $inside->save();

    $outside = Asset::query()->create([
        'serial_number' => 'SN-OUT-'.$suffix,
        'name' => 'Ausserhalb-'.$suffix,
        'model' => 'Modell',
        'itexia_id' => 'ITX-OUT-'.$suffix,
        'itexia_uuid' => 'bbbbbbbb-1111-2222-3333-44444444'.$suffix,
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);
    $outside->created_at = '2023-01-10 12:00:00';
    $outside->save();

    Livewire::actingAs($admin)
        ->test('intranet-app-assets::apps.assets.itexiageraete')
        ->set('createdAtRange', new DateRange('2024-06-01', '2024-06-30'))
        ->assertSet('createdFrom', '2024-06-01')
        ->assertSet('createdTo', '2024-06-30')
        ->assertSee('Innerhalb-'.$suffix)
        ->assertDontSee('Ausserhalb-'.$suffix);
});
