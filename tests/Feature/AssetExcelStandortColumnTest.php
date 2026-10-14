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

it('schreibt den angezeigten standort in jeden asset excel export', function (): void {
    $admin = User::factory()->create(['active' => true]);
    $admin->givePermissionTo(['see-app-assets', 'manage-app-assets']);

    $owner = User::factory()->create([
        'active' => true,
        'raum' => 'Raum-17',
    ]);
    $type = AssetType::query()->create(['name' => 'export-type-'.uniqid('', true)]);
    $vendor = AssetVendor::query()->create(['name' => 'export-vendor-'.uniqid('', true)]);
    $suffix = uniqid('', true);

    $pool = Asset::query()->create([
        'serial_number' => 'POOL-'.$suffix,
        'name' => 'Pool-'.$suffix,
        'model' => 'Modell',
        'location' => 'Lager IT',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);
    $assigned = Asset::query()->create([
        'serial_number' => 'OWNER-'.$suffix,
        'name' => 'Besitzer-'.$suffix,
        'model' => 'Modell',
        'user_id' => $owner->id,
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);
    $withoutLocation = Asset::query()->create([
        'serial_number' => 'NONE-'.$suffix,
        'name' => 'Ohne-'.$suffix,
        'model' => 'Modell',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ]);

    foreach ([
        'intranet-app-assets::apps.assets.liste',
        'intranet-app-assets::apps.assets.domaenengeraete',
        'intranet-app-assets::apps.assets.mobilgeraete',
        'intranet-app-assets::apps.assets.itexiageraete',
    ] as $component) {
        $columns = Livewire::actingAs($admin)->test($component)->instance()->getExportColumns();
        $headings = array_column($columns, 'heading');
        $ownerIndex = array_search('Besitzer', $headings, true);

        expect($ownerIndex)->not->toBeFalse()
            ->and($headings[$ownerIndex + 1] ?? null)->toBe('Standort');

        $location = $columns[$ownerIndex + 1]['value'];

        expect($location($pool))->toBe('Lager IT')
            ->and($location($assigned))->toBe('Raum-17')
            ->and($location($withoutLocation))->toBe('—');
    }
});
