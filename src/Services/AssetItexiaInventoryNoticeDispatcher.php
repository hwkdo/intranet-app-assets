<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Enums\AssetItexiaInventoryNoticeKind;
use Hwkdo\IntranetAppAssets\Jobs\NotifyAssetItexiaInventoryJob;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Support\AssetItexiaInventoryNotifyContext;

class AssetItexiaInventoryNoticeDispatcher
{
    public function dispatchIfNeeded(Asset $asset, bool $created): void
    {
        if (AssetItexiaInventoryNotifyContext::suppressed()) {
            return;
        }

        $itexiaId = trim((string) ($asset->itexia_id ?? ''));
        if ($itexiaId === '') {
            return;
        }

        $actorId = auth()->id();
        $actorId = is_int($actorId) ? $actorId : null;

        if ($created) {
            $this->dispatch(
                $asset,
                AssetItexiaInventoryNoticeKind::Created,
                $actorId,
                null,
                AssetEffectiveLocation::fromAsset($asset)['value'],
                $this->invoiceNumber($asset),
            );

            return;
        }

        $originalItexiaId = trim((string) ($asset->getOriginal('itexia_id') ?? ''));
        $itexiaNewlySet = $originalItexiaId === '' && $itexiaId !== '';
        $invoiceAdded = $this->invoiceWasAdded($asset);
        $locationFieldsChanged = $asset->wasChanged('user_id') || $asset->wasChanged('location');

        if (! $itexiaNewlySet && ! $invoiceAdded && ! $locationFieldsChanged) {
            return;
        }

        $previous = AssetEffectiveLocation::resolve(
            AssetEffectiveLocation::nullableUserId($asset->getOriginal('user_id')),
            $asset->getOriginal('location'),
        );
        $current = AssetEffectiveLocation::fromAsset($asset);
        $locationChanged = $locationFieldsChanged && $previous['value'] !== $current['value'];

        if ($itexiaNewlySet) {
            $this->dispatch(
                $asset,
                AssetItexiaInventoryNoticeKind::Created,
                $actorId,
                null,
                $current['value'],
                $this->invoiceNumber($asset),
            );

            return;
        }

        if ($invoiceAdded) {
            $invoiceNumber = $this->invoiceNumber($asset);
            if ($invoiceNumber !== null) {
                $this->dispatch(
                    $asset,
                    AssetItexiaInventoryNoticeKind::InvoiceAdded,
                    $actorId,
                    null,
                    null,
                    $invoiceNumber,
                );
            }
        }

        if (! $locationChanged) {
            return;
        }

        $this->dispatch(
            $asset,
            AssetItexiaInventoryNoticeKind::LocationChanged,
            $actorId,
            $previous['value'],
            $current['value'],
        );
    }

    private function invoiceNumber(Asset $asset): ?string
    {
        $invoice = trim((string) ($asset->invoice_number ?? ''));

        return $invoice !== '' ? $invoice : null;
    }

    private function invoiceWasAdded(Asset $asset): bool
    {
        if (! $asset->wasChanged('invoice_number')) {
            return false;
        }

        $original = trim((string) ($asset->getOriginal('invoice_number') ?? ''));

        return $original === '' && $this->invoiceNumber($asset) !== null;
    }

    private function dispatch(
        Asset $asset,
        AssetItexiaInventoryNoticeKind $kind,
        ?int $actorId,
        ?string $previousLocation,
        ?string $newLocation,
        ?string $invoiceNumber = null,
    ): void {
        NotifyAssetItexiaInventoryJob::dispatch(
            (int) $asset->id,
            $kind,
            $actorId,
            $previousLocation,
            $newLocation,
            $invoiceNumber,
        );
    }
}
