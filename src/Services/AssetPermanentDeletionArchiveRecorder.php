<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetPermanentDeletionArchive;
use Illuminate\Support\Facades\Auth;

class AssetPermanentDeletionArchiveRecorder
{
    public const SOURCE_FORCE_DELETE = 'force_delete';

    /**
     * Persistiert einen Metadaten-Snapshot unmittelbar vor dem physischen Löschen des Assets.
     *
     * @param  array<string, mixed>|null  $sourceOverride
     */
    public function record(Asset $asset, ?string $source = null, ?array $sourceOverride = null): void
    {
        $asset->loadMissing(['type', 'vendor', 'owner', 'createdBy']);

        $attributes = $asset->getAttributes();

        $payload = [
            'serial_number' => $attributes['serial_number'] ?? null,
            'model' => $attributes['model'] ?? null,
            'name' => $attributes['name'] ?? null,
            'location' => $attributes['location'] ?? null,
            'asset_type_id' => isset($attributes['asset_type_id']) ? (int) $attributes['asset_type_id'] : null,
            'asset_type_name' => $asset->type?->name,
            'asset_vendor_id' => isset($attributes['asset_vendor_id']) ? (int) $attributes['asset_vendor_id'] : null,
            'asset_vendor_name' => $asset->vendor?->name,
            'user_id' => isset($attributes['user_id']) ? (int) $attributes['user_id'] : null,
            'owner_name' => $asset->owner?->name,
            'created_by_user_id' => isset($attributes['created_by_user_id']) ? (int) $attributes['created_by_user_id'] : null,
            'created_by_name' => $asset->createdBy?->name,
            'legacy_id' => isset($attributes['legacy_id']) ? (int) $attributes['legacy_id'] : null,
            'itexia_id' => $attributes['itexia_id'] ?? null,
            'itexia_uuid' => $attributes['itexia_uuid'] ?? null,
            'order_number' => $attributes['order_number'] ?? null,
            'invoice_number' => $attributes['invoice_number'] ?? null,
            'invoice_number_pending' => (bool) ($attributes['invoice_number_pending'] ?? false),
            'imei' => $attributes['imei'] ?? null,
            'intune_device_id' => $attributes['intune_device_id'] ?? null,
            'domain_connection' => $attributes['domain_connection'] ?? null,
            'configmgr_serial_number' => $attributes['configmgr_serial_number'] ?? null,
            'configmgr_last_logon_user' => $attributes['configmgr_last_logon_user'] ?? null,
            'smbios_guid' => $attributes['smbios_guid'] ?? null,
            'is_clarification' => (bool) ($attributes['is_clarification'] ?? false),
            'is_missing' => (bool) ($attributes['is_missing'] ?? false),
            'is_in_stock' => (bool) ($attributes['is_in_stock'] ?? false),
            'itexia_actual_room_id' => isset($attributes['itexia_actual_room_id']) ? (int) $attributes['itexia_actual_room_id'] : null,
            'itexia_target_room_id' => isset($attributes['itexia_target_room_id']) ? (int) $attributes['itexia_target_room_id'] : null,
            'soft_deleted_at' => $asset->getAttribute($asset->getDeletedAtColumn())?->toIso8601String(),
            'created_at' => isset($attributes['created_at']) ? (string) $attributes['created_at'] : null,
            'updated_at' => isset($attributes['updated_at']) ? (string) $attributes['updated_at'] : null,
        ];

        if ($sourceOverride !== null) {
            $payload = array_merge($payload, $sourceOverride);
        }

        AssetPermanentDeletionArchive::query()->create([
            'original_asset_id' => (int) $asset->getKey(),
            'archived_at' => now(),
            'deleted_by_user_id' => Auth::id(),
            'source' => $source ?? self::SOURCE_FORCE_DELETE,
            'payload' => $payload,
        ]);
    }
}
