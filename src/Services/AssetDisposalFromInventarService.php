<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetNote;

class AssetDisposalFromInventarService
{
    /**
     * Soft-delete eines Assets nach Inventar-Anlagenabgang.
     *
     * Keine E-Mail über {@see AssetItexiaDeleteInventoryNotifier}: die Inventar-App
     * versendet bereits {@see \Hwkdo\IntranetAppInventar\Mail\InventarMeldungMail}.
     * Direktes Löschen in der Assets-App nutzt den Notifier weiterhin separat.
     *
     * @param  array{type_name: string, vendor_name: string, model: string, itexia_id: ?string, itexia_uuid: ?string, display_name: string}  $snapshot
     */
    public function disposeFromInventarMeldung(
        Asset $asset,
        User $actor,
        string $noteText,
        string $deleteReason,
        array $snapshot,
    ): void {
        if ($asset->trashed()) {
            return;
        }

        $asset->notes()->create([
            'note' => $noteText,
            'user_id' => $actor->id,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventDeleted,
            'user_id' => $actor->id,
            'reason' => $deleteReason,
            'meta' => [
                'source' => 'inventar_aussonderung',
                'itexia_inventory_mail' => 'deferred_to_inventar_app',
            ],
        ]);

        $asset->delete();
    }
}
