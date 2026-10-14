<?php

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Mail\AssetDeletedInItexiaInventoryMail;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Support\InventarMailRecipients;
use Hwkdo\SeventhingsLaravel\SeventhingsLaravel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AssetItexiaDeleteInventoryNotifier
{
    /**
     * @param  array{type_name: string, vendor_name: string, model: string, itexia_id: ?string, itexia_uuid: ?string, display_name: string}  $snapshot
     */
    public function notifyAfterSoftDelete(int $assetId, string $deleteReason, ?int $deletedByUserId, array $snapshot): void
    {
        $barcode = trim((string) ($snapshot['itexia_id'] ?? ''));
        if ($barcode === '') {
            return;
        }

        $asset = Asset::withTrashed()->find($assetId);
        if ($asset === null) {
            return;
        }

        $seventhingsClass = SeventhingsLaravel::class;
        if (! class_exists($seventhingsClass) || ! app()->bound($seventhingsClass)) {
            $this->createHistory($asset, AssetHistory::EventItexiaSeventhingsUnavailableOnDelete, $deletedByUserId, 'Seventhings ist nicht gebunden oder nicht verfügbar.', [
                'barcode' => $barcode,
            ]);

            return;
        }

        try {
            $client = app()->make($seventhingsClass);
            $itexiaAsset = $client->findAsset($barcode);
        } catch (Throwable $e) {
            Log::warning('AssetItexiaDeleteInventoryNotifier: findAsset fehlgeschlagen', [
                'asset_id' => $assetId,
                'barcode' => $barcode,
                'exception' => $e->getMessage(),
            ]);
            $this->createHistory($asset, AssetHistory::EventItexiaSeventhingsUnavailableOnDelete, $deletedByUserId, 'Abfrage in Seventhings fehlgeschlagen.', [
                'barcode' => $barcode,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($itexiaAsset === null) {
            $this->createHistory($asset, AssetHistory::EventItexiaNotFoundOnDelete, $deletedByUserId, 'Itexia-ID ist in Seventhings nicht bekannt (kein Treffer für Barcode).', [
                'barcode' => $barcode,
            ]);

            return;
        }

        $deletedByName = User::query()->find($deletedByUserId)?->name ?? 'Unbekannt';
        $recipients = InventarMailRecipients::resolve();

        $typeName = (string) ($snapshot['type_name'] ?? '');
        $vendorName = (string) ($snapshot['vendor_name'] ?? '');
        $modelName = (string) ($snapshot['model'] ?? '');
        $itexiaUuid = isset($snapshot['itexia_uuid']) ? (string) $snapshot['itexia_uuid'] : null;
        if ($itexiaUuid === '') {
            $itexiaUuid = null;
        }

        $mailable = new AssetDeletedInItexiaInventoryMail(
            deletedByName: $deletedByName,
            deleteReason: $deleteReason,
            typeName: $typeName,
            vendorName: $vendorName,
            modelName: $modelName,
            serialNumber: trim((string) ($asset->serial_number ?? '')),
            itexiaId: $barcode,
            itexiaUuid: $itexiaUuid,
            assetUrl: route('apps.assets.show', $asset),
        );

        try {
            Mail::to($recipients)->queue($mailable);
            $this->createHistory($asset, AssetHistory::EventItexiaInventoryMailSent, $deletedByUserId, 'Inventar-Benachrichtigung per E-Mail geplant.', [
                'barcode' => $barcode,
                'recipients' => $recipients,
            ]);
        } catch (Throwable $e) {
            Log::error('AssetItexiaDeleteInventoryNotifier: Mail-Versand fehlgeschlagen', [
                'asset_id' => $assetId,
                'exception' => $e->getMessage(),
            ]);
            $this->createHistory($asset, AssetHistory::EventItexiaInventoryMailFailed, $deletedByUserId, 'E-Mail an Inventar konnte nicht gesendet werden.', [
                'barcode' => $barcode,
                'recipients' => $recipients,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function createHistory(Asset $asset, string $event, ?int $userId, ?string $reason, array $meta = []): void
    {
        $asset->historyEntries()->create([
            'event' => $event,
            'user_id' => $userId,
            'reason' => $reason,
            'meta' => $meta !== [] ? $meta : null,
        ]);
    }
}
