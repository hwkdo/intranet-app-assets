<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\IntranetAppAssets\Mail\AssetCreatedWithItexiaMail;
use Hwkdo\IntranetAppAssets\Mail\AssetItexiaInvoiceAddedMail;
use Hwkdo\IntranetAppAssets\Mail\AssetItexiaLocationChangedMail;
use Hwkdo\IntranetAppAssets\Mail\AssetItexiaUuidLinkedMail;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Hwkdo\IntranetAppAssets\Services\InvoiceAutoResolveService;
use Hwkdo\IntranetAppAssets\Support\AssetItexiaInventoryNotifyContext;
use Hwkdo\SeventhingsLaravel\Models\Asset as ItexiaAsset;
use Hwkdo\SeventhingsLaravel\Models\Raum as ItexiaRaum;
use Hwkdo\SeventhingsLaravel\SeventhingsLaravel;
use Illuminate\Support\Facades\Mail;

function makeInventoryMailAsset(array $overrides = []): Asset
{
    $type = AssetType::query()->create(['name' => 'typ-'.uniqid('', true)]);
    $vendor = AssetVendor::query()->create(['name' => 'vendor-'.uniqid('', true)]);

    return Asset::query()->create(array_merge([
        'serial_number' => 'SN-'.uniqid('', true),
        'model' => 'Testmodell',
        'asset_type_id' => $type->id,
        'asset_vendor_id' => $vendor->id,
    ], $overrides));
}

function itexiaRoom(int $id, string $name): ItexiaRaum
{
    return new ItexiaRaum((object) [
        'id' => $id,
        'name' => $name,
        'number' => (string) $id,
    ]);
}

function itexiaObject(string $barcode, string $uuid, ?int $actualRoomId = null): ItexiaAsset
{
    return new ItexiaAsset((object) [
        'barcode' => $barcode,
        'asset_uuid' => $uuid,
        'inventory_name' => 'Test',
        'actual_room' => $actualRoomId,
    ]);
}

it('meldet ein neu angelegtes asset mit itexia-id', function (): void {
    Mail::fake();

    mock(SeventhingsLaravel::class)
        ->shouldReceive('findAsset')
        ->once()
        ->andReturn(null);

    $asset = makeInventoryMailAsset([
        'itexia_id' => '10001',
        'location' => 'Poolplatz',
    ]);

    Mail::assertQueued(AssetCreatedWithItexiaMail::class, 1);
    Mail::assertQueued(AssetCreatedWithItexiaMail::class, function (AssetCreatedWithItexiaMail $mail) use ($asset): bool {
        return $mail->hasTo('asset@hwk-do.de')
            && $mail->itexiaId === '10001'
            && $mail->serialNumber === $asset->serial_number
            && $mail->location === 'Poolplatz'
            && $mail->invoiceNumber === null
            && $mail->assetUrl === route('apps.assets.show', $asset)
            && str_contains($mail->roomSyncSummary, 'Kein Itexia-Objekt');
    });
    Mail::assertNotQueued(AssetItexiaLocationChangedMail::class);
    Mail::assertNotQueued(AssetItexiaInvoiceAddedMail::class);

    expect($asset->historyEntries()->where('event', AssetHistory::EventItexiaInventoryMailSent)->exists())->toBeTrue();
});

it('meldet kein asset ohne itexia-id', function (): void {
    Mail::fake();

    makeInventoryMailAsset([
        'location' => 'Poolplatz',
    ]);

    Mail::assertNothingQueued();
});

it('meldet das nachtraegliche setzen einer itexia-id als anlage', function (): void {
    $asset = makeInventoryMailAsset([
        'location' => 'Poolplatz',
    ]);

    Mail::fake();

    mock(SeventhingsLaravel::class)
        ->shouldReceive('findAsset')
        ->once()
        ->andReturn(null);

    $asset->update(['itexia_id' => '10002']);

    Mail::assertQueued(AssetCreatedWithItexiaMail::class, 1);
    Mail::assertNotQueued(AssetItexiaLocationChangedMail::class);
});

it('meldet einen besitzerwechsel und setzt den eindeutigen ist-raum', function (): void {
    $from = User::factory()->create(['raum' => 'AltRaum']);
    $to = User::factory()->create(['raum' => 'ZielRaum']);

    $asset = AssetItexiaInventoryNotifyContext::suppress(fn (): Asset => makeInventoryMailAsset([
        'itexia_id' => '20001',
        'itexia_uuid' => 'uuid-20001',
        'user_id' => $from->id,
    ]));

    Mail::fake();

    $client = mock(SeventhingsLaravel::class);
    $client->shouldReceive('findAsset')->once()->with('20001')->andReturn(itexiaObject('20001', 'uuid-20001'));
    $client->shouldReceive('getRaeume')->once()->andReturn(collect([itexiaRoom(15, 'ZielRaum')]));
    $client->shouldReceive('updateAsset')->once()->with('uuid-20001', ['actual_room' => 15]);

    $asset->update(['user_id' => $to->id]);

    Mail::assertQueued(AssetItexiaLocationChangedMail::class, function (AssetItexiaLocationChangedMail $mail): bool {
        return $mail->previousLocation === 'AltRaum'
            && $mail->newLocation === 'ZielRaum'
            && str_contains($mail->roomSyncSummary, 'gesetzt')
            && str_contains($mail->roomSyncSummary, 'nicht nötig');
    });
});

it('meldet eine rueckgabe auf einen pool-standort und setzt den ist-raum', function (): void {
    $owner = User::factory()->create(['raum' => 'Buero']);

    $asset = AssetItexiaInventoryNotifyContext::suppress(fn (): Asset => makeInventoryMailAsset([
        'itexia_id' => '20002',
        'itexia_uuid' => 'uuid-20002',
        'user_id' => $owner->id,
    ]));

    Mail::fake();

    $client = mock(SeventhingsLaravel::class);
    $client->shouldReceive('findAsset')->once()->andReturn(itexiaObject('20002', 'uuid-20002'));
    $client->shouldReceive('getRaeume')->once()->andReturn(collect([itexiaRoom(8, 'Lager')]));
    $client->shouldReceive('updateAsset')->once()->with('uuid-20002', ['actual_room' => 8]);

    $asset->update([
        'user_id' => null,
        'location' => 'Lager',
    ]);

    Mail::assertQueued(AssetItexiaLocationChangedMail::class, function (AssetItexiaLocationChangedMail $mail): bool {
        return $mail->previousLocation === 'Buero'
            && $mail->newLocation === 'Lager'
            && str_contains($mail->roomSyncSummary, 'ID 8');
    });
});

it('meldet einen mehrdeutigen raum ohne patch', function (): void {
    $from = User::factory()->create(['raum' => 'Start']);
    $to = User::factory()->create(['raum' => 'Halle']);

    $asset = AssetItexiaInventoryNotifyContext::suppress(fn (): Asset => makeInventoryMailAsset([
        'itexia_id' => '30001',
        'user_id' => $from->id,
    ]));

    Mail::fake();

    $client = mock(SeventhingsLaravel::class);
    $client->shouldReceive('findAsset')->once()->andReturn(itexiaObject('30001', 'uuid-30001'));
    $client->shouldReceive('getRaeume')->once()->andReturn(collect([
        itexiaRoom(1, 'Halle Nord'),
        itexiaRoom(2, 'Halle Süd'),
    ]));
    $client->shouldReceive('updateAsset')->never();

    $asset->update(['user_id' => $to->id]);

    Mail::assertQueued(AssetItexiaLocationChangedMail::class, function (AssetItexiaLocationChangedMail $mail): bool {
        return str_contains($mail->roomSyncSummary, 'nicht eindeutig')
            && str_contains($mail->roomSyncSummary, 'Halle Nord')
            && str_contains($mail->roomSyncSummary, 'Halle Süd');
    });
});

it('meldet einen nicht gefundenen raum ohne patch', function (): void {
    $from = User::factory()->create(['raum' => 'Start']);
    $to = User::factory()->create(['raum' => 'UnbekanntRaum']);

    $asset = AssetItexiaInventoryNotifyContext::suppress(fn (): Asset => makeInventoryMailAsset([
        'itexia_id' => '30002',
        'user_id' => $from->id,
    ]));

    Mail::fake();

    $client = mock(SeventhingsLaravel::class);
    $client->shouldReceive('findAsset')->once()->andReturn(itexiaObject('30002', 'uuid-30002'));
    $client->shouldReceive('getRaeume')->once()->andReturn(collect([itexiaRoom(4, 'A366')]));
    $client->shouldReceive('updateAsset')->never();

    $asset->update(['user_id' => $to->id]);

    Mail::assertQueued(AssetItexiaLocationChangedMail::class, function (AssetItexiaLocationChangedMail $mail): bool {
        return str_contains($mail->roomSyncSummary, 'kein Itexia-Raum');
    });
});

it('unterdrueckt inventar-mails im suppress-context', function (): void {
    Mail::fake();

    AssetItexiaInventoryNotifyContext::suppress(function (): void {
        makeInventoryMailAsset([
            'itexia_id' => '40001',
            'location' => 'Poolplatz',
        ]);
    });

    Mail::assertNothingQueued();
});

it('meldet kein leeren von location solange der besitzerraum gleich bleibt', function (): void {
    $owner = User::factory()->create(['raum' => 'Buero']);

    $asset = AssetItexiaInventoryNotifyContext::suppress(fn (): Asset => makeInventoryMailAsset([
        'itexia_id' => '50001',
        'user_id' => $owner->id,
    ]));

    $asset->forceFill(['location' => 'Keller'])->saveQuietly();
    $asset->refresh();

    Mail::fake();

    $asset->update(['location' => null]);

    Mail::assertNothingQueued();
});

it('weist in der anlage-mail auf eine spaeter ergaenzte rechnungsnr hin', function (): void {
    $mail = new AssetCreatedWithItexiaMail(
        actorName: 'System',
        typeName: 'Laptop',
        vendorName: 'Dell',
        modelName: 'XPS',
        serialNumber: 'SN-1',
        itexiaId: '1',
        itexiaUuid: null,
        location: 'Poolplatz',
        roomSyncSummary: 'Kein Itexia-Objekt mit diesem Barcode gefunden.',
        invoiceNumber: null,
        invoiceUrl: null,
        assetUrl: 'https://intranet.example/apps/assets/1',
    );

    $mail->assertSee('Die Rechnungsnummer liegt noch nicht vor und wird später ergänzt.');
});

it('nennt in der anlage-mail die rechnungsnr und den d3one-link wenn sie schon vorliegt', function (): void {
    configureD3OneSearchUrl();
    Mail::fake();

    mock(SeventhingsLaravel::class)
        ->shouldReceive('findAsset')
        ->once()
        ->andReturn(null);

    makeInventoryMailAsset([
        'itexia_id' => '60001',
        'invoice_number' => 'T003693386',
        'location' => 'Poolplatz',
    ]);

    Mail::assertQueued(AssetCreatedWithItexiaMail::class, function (AssetCreatedWithItexiaMail $mail): bool {
        return $mail->invoiceNumber === 'T003693386'
            && $mail->invoiceUrl === 'https://d3one.hwk-do.de/dms/r/254733d1-1130-5cad-becd-6ca766c084d6/o2/T003693386';
    });
    Mail::assertNotQueued(AssetItexiaInvoiceAddedMail::class);
});

it('meldet eine nachtraeglich gesetzte rechnungsnr mit d3one-link', function (): void {
    configureD3OneSearchUrl();

    $asset = AssetItexiaInventoryNotifyContext::suppress(fn (): Asset => makeInventoryMailAsset([
        'itexia_id' => '60002',
        'location' => 'Poolplatz',
    ]));

    Mail::fake();

    $asset->update(['invoice_number' => 'T003693386']);

    Mail::assertQueued(AssetItexiaInvoiceAddedMail::class, function (AssetItexiaInvoiceAddedMail $mail): bool {
        return $mail->hasTo('asset@hwk-do.de')
            && $mail->invoiceNumber === 'T003693386'
            && $mail->itexiaId === '60002'
            && $mail->invoiceUrl === 'https://d3one.hwk-do.de/dms/r/254733d1-1130-5cad-becd-6ca766c084d6/o2/T003693386';
    });
    Mail::assertNotQueued(AssetCreatedWithItexiaMail::class);
    Mail::assertNotQueued(AssetItexiaLocationChangedMail::class);
});

it('meldet die automatisch aus der bestellung ermittelte rechnungsnr', function (): void {
    configureD3OneSearchUrl();

    $asset = AssetItexiaInventoryNotifyContext::suppress(fn (): Asset => makeInventoryMailAsset([
        'itexia_id' => '60003',
        'order_number' => 'BEN-100',
        'location' => 'Poolplatz',
    ]));

    Mail::fake();

    mock(D3Client::class)
        ->shouldReceive('SearchResult')
        ->once()
        ->andReturn(['items' => [['id' => 'T003700001']]]);

    app(InvoiceAutoResolveService::class)->processAsset($asset, 14);

    Mail::assertQueued(AssetItexiaInvoiceAddedMail::class, function (AssetItexiaInvoiceAddedMail $mail): bool {
        return $mail->invoiceNumber === 'T003700001'
            && $mail->actorName === 'System'
            && str_contains((string) $mail->invoiceUrl, '/o2/T003700001');
    });
});

it('meldet eine erfolgreiche itexia-uuid-verknuepfung', function (): void {
    $asset = AssetItexiaInventoryNotifyContext::suppress(fn (): Asset => makeInventoryMailAsset([
        'itexia_id' => '70001',
        'serial_number' => 'SN-UUID',
    ]));

    Mail::fake();

    mock(SeventhingsLaravel::class)
        ->shouldReceive('findAsset')
        ->once()
        ->with('70001')
        ->andReturn(itexiaObject('70001', 'uuid-linked-1'));

    $this->artisan('intranet-app-assets:itexia-uuid-sync', ['--asset-id' => $asset->id])
        ->assertSuccessful();

    Mail::assertQueued(AssetItexiaUuidLinkedMail::class, function (AssetItexiaUuidLinkedMail $mail) use ($asset): bool {
        return $mail->hasTo('asset@hwk-do.de')
            && $mail->itexiaId === '70001'
            && $mail->itexiaUuid === 'uuid-linked-1'
            && $mail->serialNumber === 'SN-UUID'
            && $mail->assetUrl === route('apps.assets.show', $asset);
    });
    Mail::assertNotQueued(AssetCreatedWithItexiaMail::class);

    expect($asset->fresh()->itexia_uuid)->toBe('uuid-linked-1');
});

it('meldet keine uuid-mail wenn seventhings das asset nicht findet', function (): void {
    $asset = AssetItexiaInventoryNotifyContext::suppress(fn (): Asset => makeInventoryMailAsset([
        'itexia_id' => '70002',
    ]));

    Mail::fake();

    mock(SeventhingsLaravel::class)
        ->shouldReceive('findAsset')
        ->once()
        ->with('70002')
        ->andReturn(null);

    $this->artisan('intranet-app-assets:itexia-uuid-sync', ['--asset-id' => $asset->id])
        ->assertSuccessful();

    Mail::assertNothingQueued();
    expect($asset->fresh()->itexia_uuid)->toBeNull();
});

it('meldet keine rechnungsnr ohne itexia-id', function (): void {
    $asset = makeInventoryMailAsset([
        'order_number' => 'BEN-200',
    ]);

    Mail::fake();

    $asset->update(['invoice_number' => 'T1']);

    Mail::assertNothingQueued();
});

function configureD3OneSearchUrl(): void
{
    config([
        'd3-rest-laravel.dms-search-url' => 'https://d3one.hwk-do.de/dms/r/254733d1-1130-5cad-becd-6ca766c084d6/sr/?fulltext=',
    ]);
}
