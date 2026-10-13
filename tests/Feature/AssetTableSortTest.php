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

it('sortiert alle assets nach seriennummer wenn die spalte angeklickt wird', function (): void {
    $admin = User::factory()->create(['active' => true]);
    $admin->givePermissionTo(['see-app-assets', 'manage-app-assets']);

    $type = AssetType::query()->create(['name' => 'sort-type-'.uniqid('', true)]);
    $vendor = AssetVendor::query()->create(['name' => 'sort-vendor-'.uniqid('', true)]);
    $suffix = uniqid('', true);
    $serialFirst = 'AAA-SORT-'.$suffix;
    $serialLast = 'ZZZ-SORT-'.$suffix;

    Asset::query()->create([
        'serial_number' => $serialLast,
        'name' => 'Sort-Name-'.$suffix,
        'model' => 'Sort-Modell-'.$suffix,
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    Asset::query()->create([
        'serial_number' => $serialFirst,
        'name' => 'Sort-Name-'.$suffix,
        'model' => 'Sort-Modell-'.$suffix,
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    Livewire::actingAs($admin)
        ->test('intranet-app-assets::apps.assets.liste')
        ->call('sortBy', 'not-a-column')
        ->assertSet('sortColumn', '')
        ->call('sortBy', 'serial_number')
        ->assertSet('sortColumn', 'serial_number')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder([$serialFirst, $serialLast])
        ->call('sortBy', 'serial_number')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder([$serialLast, $serialFirst])
        ->call('sortBy', 'owner')
        ->assertSet('sortColumn', 'owner')
        ->assertSet('sortDirection', 'asc')
        ->assertSee($serialFirst)
        ->assertSee($serialLast);
});
