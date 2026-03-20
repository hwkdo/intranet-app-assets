<?php

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Mail\AssetDeletedInItexiaInventoryMail;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AssetItexiaDeleteInventoryNotifier
{
    private const DEFAULT_INVENTORY_MAIL = 'asset@hwk-do.de';

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

        $seventhingsClass = \Hwkdo\SeventhingsLaravel\SeventhingsLaravel::class;
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
        $recipients = $this->parseRecipientEmails($this->resolveRecipientsRaw());

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
            itexiaId: $barcode,
            itexiaUuid: $itexiaUuid,
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

    private function resolveRecipientsRaw(): string
    {
        $settings = IntranetAppAssetsSettings::current()?->settings;
        if ($settings instanceof AppSettings) {
            return trim($settings->empfaengerInventarMails);
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function parseRecipientEmails(string $raw): array
    {
        if ($raw === '') {
            return [self::DEFAULT_INVENTORY_MAIL];
        }

        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $valid = [];
        foreach ($parts as $part) {
            $email = trim((string) $part);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $email;
            }
        }

        return $valid !== [] ? $valid : [self::DEFAULT_INVENTORY_MAIL];
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
