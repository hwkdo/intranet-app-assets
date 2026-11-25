<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Events\AssetHandoverChanged;
use Hwkdo\IntranetAppAssets\Events\AssetsDashboardChanged;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Illuminate\Support\Facades\Auth;

class AssetDashboardBroadcaster
{
    /**
     * Felder, die Admin-Widgets auf dem Dashboard anzeigen.
     *
     * @var list<string>
     */
    public const WIDGET_FIELDS = [
        'is_missing',
        'is_clarification',
        'invoice_number',
        'invoice_number_pending',
        'itexia_id',
        'itexia_uuid',
        'serial_number',
        'configmgr_serial_number',
    ];

    public function assetSaved(Asset $asset): void
    {
        if (! Auth::check()) {
            return;
        }

        if (! $asset->wasRecentlyCreated && ! $asset->wasChanged(self::WIDGET_FIELDS)) {
            return;
        }

        AssetsDashboardChanged::dispatch();
    }

    public function handoverSaved(Handover $handover): void
    {
        if (! Auth::check()) {
            return;
        }

        if (! $handover->wasRecentlyCreated && ! $handover->wasChanged([
            'confirmed_at',
            'rejected_at',
            'recipient_user_id',
            'superseded_at',
        ])) {
            return;
        }

        AssetsDashboardChanged::dispatch();
        $this->notifyRecipients($handover);
    }

    public function returnSaved(AssetReturn $return): void
    {
        if (! Auth::check()) {
            return;
        }

        if (! $return->wasRecentlyCreated && ! $return->wasChanged(['completed_at', 'received_confirmed_at'])) {
            return;
        }

        AssetsDashboardChanged::dispatch();
    }

    public function batchFinished(): void
    {
        AssetsDashboardChanged::dispatch();
    }

    private function notifyRecipients(Handover $handover): void
    {
        $userIds = [];

        if ($handover->recipient_user_id !== null) {
            $userIds[] = (int) $handover->recipient_user_id;
        }

        $previousRecipientId = $handover->getOriginal('recipient_user_id');
        if (is_numeric($previousRecipientId)) {
            $userIds[] = (int) $previousRecipientId;
        }

        foreach (array_unique(array_filter($userIds, static fn (int $userId): bool => $userId > 0)) as $userId) {
            AssetHandoverChanged::dispatch($userId);
        }
    }
}
