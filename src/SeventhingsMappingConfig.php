<?php

namespace Hwkdo\IntranetAppAssets;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;

/**
 * Konfiguration der verfügbaren Attribute für das Seventhings-Mapping
 * und Hilfsmethoden zum Auslesen der Werte.
 */
class SeventhingsMappingConfig
{
    /**
     * Itexia-Attribut-Key => API-Feldname für Seventhings objects/update.
     *
     * @return array<string, string>
     */
    public static function itexiaAttributeToApiField(): array
    {
        return [
            'barcode' => 'barcode',
            'beschreibung' => 'inventory_name',
            'sn' => 'custom_4',
            'preis' => 'preis_hist_anschaffungskosten_eff27c3b',
            'kostenstelle' => 'custom_84',
            'datev_nr' => 'custom_78',
            'einheit' => 'custom_93',
            'lieferdatum' => 'purchasing_date',
            'raum_soll' => 'target_room',
            'raum_ist' => 'actual_room',
            'konto' => 'custom_79',
            'kontobeschriftung' => 'custom_80',
            'nutzungsart' => 'nutzungsart_c855189d',
            'versicherungsart' => 'versicherungsart_43f20af4',
            'nutzungsdauer' => 'custom_83',
            'halbwertszeit' => 'technische_halbwertszeit_16703785',
            'geraeteart' => 'ger_teart_b9efdd60',
        ];
    }
    /**
     * Lokale Asset-Attribute (key => Anzeigelabel).
     *
     * @return array<string, string>
     */
    public static function localAttributes(): array
    {
        return [
            'serial_number' => 'Seriennummer',
            'model' => 'Modell',
            'vendor' => 'Hersteller',
            'vendor_model' => 'Beschreibung (Hersteller + Modell)',
            'name' => 'Name',
            'display_name' => 'Anzeigename',
            'location' => 'Standort',
            'itexia_id' => 'Itexia-ID / Barcode',
            'order_number' => 'Bestellnummer',
            'invoice_number' => 'Rechnungsnummer',
            'type' => 'Typ',
            'owner' => 'Besitzer',
        ];
    }

    /**
     * Itexia/Seventhings-Attribute (key => Anzeigelabel).
     * Keys entsprechen den Attributen am Itexia-Asset-Model.
     *
     * @return array<string, string>
     */
    public static function itexiaAttributes(): array
    {
        return [
            'barcode' => 'Barcode',
            'beschreibung' => 'Beschreibung',
            'sn' => 'Seriennummer',
            'preis' => 'Preis',
            'kostenstelle' => 'Kostenstelle',
            'datev_nr' => 'DATEV-Nr.',
            'einheit' => 'Einheit',
            'lieferdatum' => 'Lieferdatum',
            'raum_soll' => 'Raum Soll',
            'raum_ist' => 'Raum Ist',
            'konto' => 'Konto',
            'kontobeschriftung' => 'Kontobeschriftung',
            'nutzungsart' => 'Nutzungsart',
            'versicherungsart' => 'Versicherungsart',
            'nutzungsdauer' => 'Nutzungsdauer',
            'gefoerdert' => 'Gefördert',
            'external_id' => 'Externe ID',
            'halbwertszeit' => 'Halbwertszeit',
            'geraeteart' => 'Geräteart',
        ];
    }

    /**
     * Wert eines lokalen Attributs von einem Asset auslesen.
     */
    public static function getLocalValue(Asset $asset, string $localAttribute): mixed
    {
        return match ($localAttribute) {
            'serial_number' => $asset->serial_number,
            'model' => $asset->model,
            'vendor' => $asset->vendor?->name,
            'vendor_model' => trim(($asset->vendor?->name ?? '').' '.($asset->model ?? '')),
            'name' => $asset->name,
            'display_name' => $asset->display_name,
            'location' => $asset->location,
            'itexia_id' => $asset->itexia_id,
            'order_number' => $asset->order_number,
            'invoice_number' => $asset->invoice_number,
            'type' => $asset->type?->name,
            'owner' => $asset->owner?->name,
            default => null,
        };
    }

    /**
     * Wert eines Itexia-Attributs aus dem (bereits aufbereiteten) itexia-Daten-Array.
     * Das Array kann aus openItexiaModal kommen (label => value) oder aus
     * getItexiaDataArray($itexiaAsset) (key => value).
     *
     * @param  array<string, mixed>  $itexiaData  key => value (Itexia-Attributkeys)
     */
    public static function getItexiaValue(array $itexiaData, string $itexiaAttribute): mixed
    {
        return $itexiaData[$itexiaAttribute] ?? null;
    }

    /**
     * Itexia-Asset in ein einfaches Array (key => value) umwandeln,
     * damit wir Werte per Key vergleichen können. Raum-Soll/Ist als String.
     *
     * @return array<string, mixed>
     */
    public static function itexiaAssetToArray(object $itexiaAsset): array
    {
        $raumSoll = $itexiaAsset->raum_soll ?? null;
        $raumIst = $itexiaAsset->raum_ist ?? null;

        return [
            'barcode' => $itexiaAsset->barcode ?? null,
            'beschreibung' => $itexiaAsset->beschreibung ?? null,
            'sn' => $itexiaAsset->sn ?? null,
            'preis' => $itexiaAsset->preis ?? null,
            'kostenstelle' => $itexiaAsset->kostenstelle ?? null,
            'datev_nr' => $itexiaAsset->datev_nr ?? null,
            'einheit' => $itexiaAsset->einheit ?? null,
            'lieferdatum' => $itexiaAsset->lieferdatum ?? null,
            'raum_soll' => is_object($raumSoll) ? ($raumSoll->name ?? (string) $raumSoll) : $raumSoll,
            'raum_ist' => is_object($raumIst) ? ($raumIst->name ?? (string) $raumIst) : $raumIst,
            'konto' => $itexiaAsset->konto ?? null,
            'kontobeschriftung' => $itexiaAsset->kontobeschriftung ?? null,
            'nutzungsart' => $itexiaAsset->nutzungsart ?? null,
            'versicherungsart' => $itexiaAsset->versicherungsart ?? null,
            'nutzungsdauer' => $itexiaAsset->nutzungsdauer ?? null,
            'gefoerdert' => isset($itexiaAsset->gefoerdert) ? ($itexiaAsset->gefoerdert ? 'Ja' : 'Nein') : null,
            'external_id' => $itexiaAsset->external_id ?? null,
            'halbwertszeit' => $itexiaAsset->halbwertszeit ?? null,
            'geraeteart' => $itexiaAsset->geraeteart ?? null,
        ];
    }

    /**
     * Ermittelt die Seventhings-Objekt-UUID für PATCH object/{objectUuid} (Customer API).
     * Die API erwartet die UUID im Pfad, nicht den Barcode oder eine numerische ID.
     * Zuerst Konfiguration (seventhings_object_id_key), danach uuid, dann Fallbacks.
     */
    public static function getSeventhingsObjectId(object $itexiaAsset): mixed
    {
        if (! method_exists($itexiaAsset, 'getRawData')) {
            return null;
        }

        $row = $itexiaAsset->getRawData();
        if ($row === null) {
            return null;
        }

        $configKey = config('intranet-app-assets.seventhings_object_id_key');
        if (is_string($configKey) && $configKey !== '') {
            $value = property_exists($row, $configKey) ? $row->{$configKey} : $itexiaAsset->getRawData($configKey);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        $uuidKeys = ['asset_uuid', 'uuid', 'object_id', 'internal_id', 'id'];
        foreach ($uuidKeys as $key) {
            $value = property_exists($row, $key) ? $row->{$key} : null;
            if ($value === null || $value === '') {
                continue;
            }
            if (in_array($key, ['asset_uuid', 'uuid', 'object_id', 'internal_id'], true) && is_string($value)) {
                return $value;
            }
            if (is_numeric($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Zwei Werte normalisiert vergleichen (für Anzeige).
     */
    public static function valuesMatch(mixed $local, mixed $itexia): bool
    {
        $a = self::normalizeValue($local);
        $b = self::normalizeValue($itexia);

        return $a === $b;
    }

    /**
     * Wert von Itexia auf das lokale Asset anwenden („Von Itexia übernehmen“).
     * Gibt true zurück wenn das Setzen unterstützt und durchgeführt wurde.
     */
    public static function setLocalValue(Asset $asset, string $localAttribute, mixed $value): bool
    {
        $value = self::normalizeValue($value);
        if ($value === '' && ! in_array($localAttribute, ['name', 'location', 'order_number', 'invoice_number'], true)) {
            return false;
        }

        return match ($localAttribute) {
            'serial_number' => self::updateAssetColumn($asset, 'serial_number', $value),
            'model' => self::updateAssetColumn($asset, 'model', $value),
            'name' => self::updateAssetColumn($asset, 'name', $value),
            'location' => self::updateAssetColumn($asset, 'location', $value),
            'itexia_id' => self::updateAssetColumn($asset, 'itexia_id', $value),
            'order_number' => self::updateAssetColumn($asset, 'order_number', $value),
            'invoice_number' => self::updateAssetColumn($asset, 'invoice_number', $value),
            'vendor' => self::setVendorByName($asset, $value),
            'type' => self::setTypeByName($asset, $value),
            'vendor_model' => self::setVendorAndModelFromDescription($asset, $value),
            default => false,
        };
    }

    private static function updateAssetColumn(Asset $asset, string $column, string $value): bool
    {
        $asset->update([$column => $value === '' ? null : $value]);

        return true;
    }

    private static function setVendorByName(Asset $asset, string $name): bool
    {
        $vendor = AssetVendor::where('name', $name)->first();
        if (! $vendor) {
            return false;
        }
        $asset->update(['asset_vendor_id' => $vendor->id]);

        return true;
    }

    private static function setTypeByName(Asset $asset, string $name): bool
    {
        $type = AssetType::where('name', $name)->first();
        if (! $type) {
            return false;
        }
        $asset->update(['asset_type_id' => $type->id]);

        return true;
    }

    /**
     * Itexia „Beschreibung“ (Hersteller + Modell) in vendor und model aufteilen und setzen.
     * Sucht den längsten passenden Hersteller-Namen am Anfang der Beschreibung.
     */
    private static function setVendorAndModelFromDescription(Asset $asset, string $description): bool
    {
        $description = trim($description);
        if ($description === '') {
            return false;
        }

        $vendors = AssetVendor::orderByRaw('LENGTH(name) DESC')->get();
        $vendorId = null;
        $model = $description;

        foreach ($vendors as $vendor) {
            $name = trim($vendor->name);
            if ($name === '') {
                continue;
            }
            if (stripos($description, $name) === 0) {
                $vendorId = $vendor->id;
                $model = trim(mb_substr($description, mb_strlen($name)));
                break;
            }
        }

        $updates = ['model' => $model !== '' ? $model : null];
        if ($vendorId !== null) {
            $updates['asset_vendor_id'] = $vendorId;
        }
        $asset->update($updates);

        return true;
    }

    public static function normalizeValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }
}
