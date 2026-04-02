<?php

namespace Hwkdo\IntranetAppAssets\Listeners;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\SeventhingsLaravel\Events\ItexiaAssetActualRoomUpdated;

class UpdateCachedItexiaActualRoom
{
    public function handle(ItexiaAssetActualRoomUpdated $event): void
    {
        $payload = [
            'itexia_actual_room_id' => $event->actualRoomId,
            'itexia_rooms_synced_at' => now(),
        ];

        $uuid = strtolower(trim($event->objectUuid));
        $updated = 0;

        if ($uuid !== '') {
            $updated = (int) Asset::query()
                ->whereRaw('LOWER(TRIM(itexia_uuid)) = ?', [$uuid])
                ->update($payload);
        }

        if ($updated === 0) {
            $barcode = $event->barcode !== null ? trim($event->barcode) : '';
            if ($barcode !== '') {
                Asset::query()
                    ->where('itexia_id', $barcode)
                    ->update($payload);
            }
        }
    }
}
