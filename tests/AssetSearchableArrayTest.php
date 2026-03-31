<?php

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetAttachment;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetNote;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Hwkdo\IntranetAppAssets\Models\Handover;

it('builds a searchable payload with core fields and aggregated note history text', function () {
    $asset = new Asset([
        'id' => 42,
        'name' => 'Arbeitsplatz Laptop',
        'model' => 'ThinkPad X1',
        'location' => 'Büro 2.12',
        'serial_number' => 'SN-12345',
        'imei' => '990001234567890',
        'itexia_id' => 'ITX-77',
        'itexia_uuid' => 'uuid-77',
        'intune_device_id' => 'intune-abc',
        'configmgr_serial_number' => 'cfg-serial',
        'smbios_guid' => 'guid-123',
        'order_number' => 'BEN-44',
        'invoice_number' => 'RE-99',
        'domain_connection' => 'AD-HWK',
        'configmgr_last_logon_user' => 'max.muster',
        'is_missing' => true,
        'is_clarification' => true,
        'invoice_number_pending' => true,
        'created_at' => now(),
    ]);

    $asset->setRelation('owner', new User(['vorname' => 'Max', 'nachname' => 'Muster']));
    $asset->setRelation('type', new AssetType(['name' => 'Laptop']));
    $asset->setRelation('vendor', new AssetVendor(['name' => 'Lenovo']));

    $history = new AssetHistory([
        'event' => AssetHistory::EventUpdated,
        'reason' => 'Besitzer wurde geändert',
        'meta' => ['field' => 'user_id', 'from' => 'Anna', 'to' => 'Max'],
    ]);

    $assetNote = new AssetNote(['note' => 'Direkte Asset-Notiz']);
    $handoverNote = new AssetNote(['note' => 'Übergabe-Notiz']);
    $returnNote = new AssetNote(['note' => 'Rückgabe-Notiz']);
    $attachmentNote = new AssetNote(['note' => 'Anhang-Notiz']);

    $assetReturn = new AssetReturn();
    $assetReturn->setRelation('notes', collect([$returnNote]));

    $handover = new Handover();
    $handover->setRelation('notes', collect([$handoverNote]));
    $handover->setRelation('assetReturn', $assetReturn);

    $attachment = new AssetAttachment();
    $attachment->setRelation('notes', collect([$attachmentNote]));

    $asset->setRelation('historyEntries', collect([$history]));
    $asset->setRelation('notes', collect([$assetNote]));
    $asset->setRelation('handovers', collect([$handover]));
    $asset->setRelation('attachments', collect([$attachment]));

    $searchable = $asset->toSearchableArray();

    expect($searchable['id'])->toBe('42')
        ->and($searchable['name'])->toBe('Arbeitsplatz Laptop')
        ->and($searchable['owner_name'])->toBe('Max Muster')
        ->and($searchable['owner_vorname'])->toBe('Max')
        ->and($searchable['owner_nachname'])->toBe('Muster')
        ->and($searchable['serial_number'])->toBe('SN-12345')
        ->and($searchable['itexia_uuid'])->toBe('uuid-77')
        ->and($searchable['status_tokens'])->toBe('missing clarification invoice_pending')
        ->and($searchable['history_text'])->toContain('Besitzer wurde geändert')
        ->and($searchable['notes_text'])->toContain('Direkte Asset-Notiz')
        ->and($searchable['notes_text'])->toContain('Übergabe-Notiz')
        ->and($searchable['notes_text'])->toContain('Rückgabe-Notiz')
        ->and($searchable['notes_text'])->toContain('Anhang-Notiz');
});
