<?php

namespace Hwkdo\IntranetAppAssets;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\SeventhingsLaravel\Support\ItexiaActualRoomResolver;

class ItexiaCreation
{
    /**
     * Prüft, ob das Asset in Itexia/Seventhings angelegt werden darf.
     * Voraussetzungen: Typ itexia_creation_allowed, itexia_id gesetzt, keine itexia_uuid,
     * und je nach Einstellung keine Rechnungsnummer.
     */
    public static function canCreateInItexia(Asset $asset): bool
    {
        if ($asset->type?->itexia_creation_allowed !== true) {
            return false;
        }

        $itexiaId = trim((string) ($asset->itexia_id ?? ''));
        if ($itexiaId === '') {
            return false;
        }

        if ($asset->itexia_uuid !== null && trim((string) $asset->itexia_uuid) !== '') {
            return false;
        }

        $settings = IntranetAppAssetsSettings::current()?->settings;
        $allowWithInvoice = $settings->allowCreateInItexiaWithInvoiceNumber ?? false;

        if (! $allowWithInvoice && filled($asset->invoice_number)) {
            return false;
        }

        return true;
    }

    /**
     * Baut den Payload für das Anlegen eines Objekts in Itexia/Seventhings.
     * Enthält barcode, custom_78, inventory_name, custom_4; optional rechnungsnummer_b0eb3192
     * und actual_room (wenn ein passender Raum gefunden wird).
     *
     * @return array<string, mixed>
     */
    public static function buildCreatePayload(Asset $asset): array
    {
        $asset->loadMissing(['type', 'vendor', 'owner.standort']);

        $barcode = trim((string) ($asset->itexia_id ?? ''));
        $payload = [
            'barcode' => $barcode,
            'custom_78' => $barcode,
            'inventory_name' => trim(($asset->vendor?->name ?? '').' '.($asset->model ?? '')),
            'custom_4' => $asset->serial_number ? trim((string) $asset->serial_number) : null,
            'custom_93' => $asset->type?->name ? trim((string) $asset->type->name) : null,
        ];

        $settings = IntranetAppAssetsSettings::current()?->settings;
        if ($settings && ($settings->allowCreateInItexiaWithInvoiceNumber ?? false) && filled($asset->invoice_number)) {
            $payload['rechnungsnummer_b0eb3192'] = trim((string) $asset->invoice_number);
        }

        $roomId = self::resolveActualRoomId($asset);
        if ($roomId !== null) {
            $payload['actual_room'] = $roomId;
        }

        return $payload;
    }

    /**
     * Überträgt ein vorhandenes lokales Asset-Bild in Itexia (Feld "picture"), falls vorhanden.
     * Falls kein lokales Bild existiert, wird nichts getan.
     */
    public static function syncLocalImageToItexia(Asset $asset, object $client, string $objectUuid): void
    {
        $media = $asset->getFirstMedia('image');
        if ($media === null) {
            return;
        }

        $contents = file_get_contents($media->getPath());
        if (! is_string($contents) || $contents === '') {
            return;
        }

        $fileUuid = $client->uploadFile($contents, $media->file_name);
        try {
            $client->addFileToObject($objectUuid, 'picture', $fileUuid);
        } catch (\Throwable) {
            // Fallback: einige Instanzen akzeptieren add-file nicht im erwarteten Schema.
            // Dann Attachment-Feld direkt per PATCH setzen.
            $client->updateAsset($objectUuid, ['picture' => [$fileUuid]]);
        }
    }

    /**
     * Sucht anhand Raum des Besitzers (Vorrang) oder Standort des Assets (location) einen Itexia-Raum und liefert dessen ID.
     */
    public static function resolveActualRoomId(Asset $asset): ?int
    {
        $asset->loadMissing('owner');
        $search = '';
        $owner = $asset->owner;
        if ($owner !== null) {
            $search = trim((string) ($owner->raum ?? ''));
        }
        if ($search === '') {
            $search = trim((string) ($asset->location ?? ''));
        }
        if ($search === '') {
            return null;
        }

        $seventhingsClass = \Hwkdo\SeventhingsLaravel\SeventhingsLaravel::class;
        if (! class_exists($seventhingsClass) || ! app()->bound($seventhingsClass)) {
            return null;
        }

        try {
            $client = app()->make($seventhingsClass);
        } catch (\Throwable) {
            return null;
        }

        return ItexiaActualRoomResolver::resolveRoomIdFromSearchHint($client, $search);
    }
}
