<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Jobs;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Enums\AssetItexiaInventoryNoticeKind;
use Hwkdo\IntranetAppAssets\Mail\AssetCreatedWithItexiaMail;
use Hwkdo\IntranetAppAssets\Mail\AssetItexiaInvoiceAddedMail;
use Hwkdo\IntranetAppAssets\Mail\AssetItexiaLocationChangedMail;
use Hwkdo\IntranetAppAssets\Mail\AssetItexiaUuidLinkedMail;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Services\AssetItexiaActualRoomSync;
use Hwkdo\IntranetAppAssets\Support\DmsLinkHelper;
use Hwkdo\IntranetAppAssets\Support\InventarMailRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyAssetItexiaInventoryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $assetId,
        public AssetItexiaInventoryNoticeKind $kind,
        public ?int $actorId,
        public ?string $previousLocation,
        public ?string $newLocation,
        public ?string $invoiceNumber = null,
    ) {}

    public function handle(AssetItexiaActualRoomSync $roomSync): void
    {
        $asset = Asset::query()->with(['type', 'vendor'])->find($this->assetId);
        if ($asset === null) {
            return;
        }

        $barcode = trim((string) ($asset->itexia_id ?? ''));
        if ($barcode === '') {
            return;
        }

        $syncsRoom = ! in_array($this->kind, [
            AssetItexiaInventoryNoticeKind::InvoiceAdded,
            AssetItexiaInventoryNoticeKind::UuidLinked,
        ], true);
        $result = $syncsRoom ? $roomSync->sync($asset) : null;
        $invoiceNumber = $this->invoiceNumber !== null ? trim($this->invoiceNumber) : '';
        $invoiceNumber = $invoiceNumber !== '' ? $invoiceNumber : null;
        $invoiceUrl = $this->invoiceUrl($invoiceNumber);

        $recipients = InventarMailRecipients::resolve();
        $actorName = $this->actorName();
        $typeName = (string) ($asset->type?->name ?? '');
        $vendorName = (string) ($asset->vendor?->name ?? '');
        $modelName = (string) ($asset->model ?? '');
        $serialNumber = trim((string) ($asset->serial_number ?? ''));
        $itexiaUuid = trim((string) ($asset->itexia_uuid ?? ''));
        $itexiaUuid = $itexiaUuid !== '' ? $itexiaUuid : null;
        $assetUrl = route('apps.assets.show', $asset);

        $mailable = match ($this->kind) {
            AssetItexiaInventoryNoticeKind::Created => new AssetCreatedWithItexiaMail(
                actorName: $actorName,
                typeName: $typeName,
                vendorName: $vendorName,
                modelName: $modelName,
                serialNumber: $serialNumber,
                itexiaId: $barcode,
                itexiaUuid: $itexiaUuid,
                location: $this->newLocation,
                roomSyncSummary: $result?->summary ?? '',
                invoiceNumber: $invoiceNumber,
                invoiceUrl: $invoiceUrl,
                assetUrl: $assetUrl,
            ),
            AssetItexiaInventoryNoticeKind::LocationChanged => new AssetItexiaLocationChangedMail(
                actorName: $actorName,
                typeName: $typeName,
                vendorName: $vendorName,
                modelName: $modelName,
                serialNumber: $serialNumber,
                itexiaId: $barcode,
                itexiaUuid: $itexiaUuid,
                previousLocation: $this->previousLocation,
                newLocation: $this->newLocation,
                roomSyncSummary: $result?->summary ?? '',
                assetUrl: $assetUrl,
            ),
            AssetItexiaInventoryNoticeKind::InvoiceAdded => $invoiceNumber === null
                ? null
                : new AssetItexiaInvoiceAddedMail(
                    actorName: $actorName,
                    typeName: $typeName,
                    vendorName: $vendorName,
                    modelName: $modelName,
                    serialNumber: $serialNumber,
                    itexiaId: $barcode,
                    itexiaUuid: $itexiaUuid,
                    invoiceNumber: $invoiceNumber,
                    invoiceUrl: $invoiceUrl,
                    assetUrl: $assetUrl,
                ),
            AssetItexiaInventoryNoticeKind::UuidLinked => $itexiaUuid === null
                ? null
                : new AssetItexiaUuidLinkedMail(
                    actorName: $actorName,
                    typeName: $typeName,
                    vendorName: $vendorName,
                    modelName: $modelName,
                    serialNumber: $serialNumber,
                    itexiaId: $barcode,
                    itexiaUuid: $itexiaUuid,
                    invoiceNumber: $invoiceNumber,
                    invoiceUrl: $invoiceUrl,
                    assetUrl: $assetUrl,
                ),
        };

        if ($mailable === null) {
            return;
        }

        $meta = [
            'kind' => $this->kind->value,
            'barcode' => $barcode,
            'recipients' => $recipients,
            'room_sync_status' => $result?->status->value,
            'room_sync_summary' => $result?->summary,
            'previous_location' => $this->previousLocation,
            'new_location' => $this->newLocation,
            'invoice_number' => $invoiceNumber,
            'invoice_url' => $invoiceUrl,
        ];

        try {
            Mail::to($recipients)->queue($mailable);
            $this->createHistory($asset, AssetHistory::EventItexiaInventoryMailSent, 'Inventar-Benachrichtigung per E-Mail geplant.', $meta);
        } catch (Throwable $e) {
            Log::error('NotifyAssetItexiaInventoryJob: Mail-Versand fehlgeschlagen', [
                'asset_id' => $asset->id,
                'exception' => $e->getMessage(),
            ]);
            $meta['error'] = $e->getMessage();
            $this->createHistory($asset, AssetHistory::EventItexiaInventoryMailFailed, 'E-Mail an Inventar konnte nicht gesendet werden.', $meta);
        }
    }

    private function invoiceUrl(?string $invoiceNumber): ?string
    {
        $searchUrl = config('d3-rest-laravel.dms-search-url', '');
        $baseUrl = DmsLinkHelper::baseUrlFromDmsSearchUrl(is_string($searchUrl) ? $searchUrl : '');

        return DmsLinkHelper::invoiceUrl($baseUrl, $invoiceNumber);
    }

    private function actorName(): string
    {
        if ($this->actorId === null) {
            return 'System';
        }

        $name = User::query()->find($this->actorId)?->name;

        return is_string($name) && trim($name) !== '' ? $name : 'System';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function createHistory(Asset $asset, string $event, string $reason, array $meta): void
    {
        $asset->historyEntries()->create([
            'event' => $event,
            'user_id' => $this->actorId,
            'reason' => $reason,
            'meta' => $meta,
        ]);
    }
}
