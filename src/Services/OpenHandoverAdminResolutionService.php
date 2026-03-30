<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Illuminate\Support\Facades\DB;

class OpenHandoverAdminResolutionService
{
    public const PENDING_RESOLUTION_SESSION_KEY = 'intranet_app_assets.open_handover_resolve_pending';

    public const ResolutionNewOwner = 'new_owner';

    public const ResolutionSetLocation = 'set_location';

    public const ResolutionMarkMissing = 'mark_missing';

    public function resolve(
        Handover $handover,
        int $adminUserId,
        string $resolution,
        ?int $newOwnerUserId,
        ?string $location,
        ?string $note = null,
    ): void {
        if ($handover->isConfirmed() || $handover->isRejected()) {
            throw new \InvalidArgumentException('Übergabe kann nicht aufgelöst werden.');
        }

        $asset = $handover->asset;
        if ($asset === null) {
            throw new \InvalidArgumentException('Asset fehlt.');
        }

        $recipientId = $handover->recipient_user_id;
        $recipientName = $handover->recipient?->name;

        $note = $note !== null ? trim($note) : '';

        AssetAuditContext::runWith('assets.open_handover.resolve', function () use ($handover, $asset, $adminUserId, $resolution, $newOwnerUserId, $location, $recipientId, $recipientName, $note): void {
            DB::transaction(function () use ($handover, $asset, $adminUserId, $resolution, $newOwnerUserId, $location, $recipientId, $recipientName, $note): void {
                $baseMeta = [
                    'handover_id' => $handover->id,
                    'former_recipient_user_id' => $recipientId,
                    'former_recipient_name' => $recipientName,
                    'resolution' => $resolution,
                    'bulk_note' => $note !== '' ? $note : null,
                ];

                $asset->historyEntries()->create([
                    'event' => AssetHistory::EventPendingHandoverAdminAcknowledged,
                    'user_id' => $adminUserId,
                    'reason' => 'Admin übernimmt offene Übergabe zur Auflösung.',
                    'meta' => [
                        'handover_id' => $handover->id,
                        'recipient_user_id' => $recipientId,
                        'recipient_name' => $recipientName,
                    ],
                ]);

                match ($resolution) {
                    self::ResolutionNewOwner => $this->applyNewOwner($asset, $handover, $adminUserId, $newOwnerUserId, $baseMeta, $note),
                    self::ResolutionSetLocation => $this->applySetLocation($asset, $handover, $adminUserId, $location, $baseMeta, $note),
                    self::ResolutionMarkMissing => $this->applyMarkMissing($asset, $handover, $adminUserId, $baseMeta, $note),
                    default => throw new \InvalidArgumentException('Unbekannte Auflösung.'),
                };
            });
        });
    }

    private function applyNewOwner(Asset $asset, Handover $handover, int $adminUserId, ?int $newOwnerUserId, array $baseMeta, string $note): void
    {
        if ($newOwnerUserId === null || $newOwnerUserId < 1) {
            throw new \InvalidArgumentException('Neuer Besitzer erforderlich.');
        }

        $this->deleteHandoverWithRelations($handover);

        $asset->update([
            'user_id' => $newOwnerUserId,
            'is_missing' => false,
            'is_clarification' => false,
        ]);
        $asset->refresh();
        $asset->ensureHandoverForOwner();

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventPendingHandoverAdminResolvedNewOwner,
            'user_id' => $adminUserId,
            'reason' => $note !== '' ? $note : 'Offene Übergabe: Neuer Besitzer zugewiesen.',
            'meta' => array_merge($baseMeta, [
                'new_owner_user_id' => $newOwnerUserId,
            ]),
        ]);
    }

    private function applySetLocation(Asset $asset, Handover $handover, int $adminUserId, ?string $location, array $baseMeta, string $note): void
    {
        $location = $location !== null ? trim($location) : '';
        if ($location === '') {
            throw new \InvalidArgumentException('Standort erforderlich.');
        }

        $this->deleteHandoverWithRelations($handover);

        $asset->update([
            'user_id' => null,
            'location' => $location,
            'is_missing' => false,
            'is_clarification' => false,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventPendingHandoverAdminResolvedLocation,
            'user_id' => $adminUserId,
            'reason' => $note !== '' ? $note : 'Offene Übergabe: Besitzer entfernt, Standort gesetzt.',
            'meta' => array_merge($baseMeta, [
                'location' => $location,
            ]),
        ]);
    }

    private function applyMarkMissing(Asset $asset, Handover $handover, int $adminUserId, array $baseMeta, string $note): void
    {
        $this->deleteHandoverWithRelations($handover);

        $asset->update([
            'user_id' => null,
            'is_missing' => true,
            'is_clarification' => false,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventPendingHandoverAdminResolvedMissing,
            'user_id' => $adminUserId,
            'reason' => $note !== '' ? $note : 'Offene Übergabe: Als vermisst markiert, Besitzer entfernt.',
            'meta' => $baseMeta,
        ]);
    }

    private function deleteHandoverWithRelations(Handover $handover): void
    {
        $returns = $handover->assetReturns;
        foreach ($returns as $return) {
            $return->notes()->delete();
            $return->delete();
        }

        $handover->notes()->delete();
        $handover->delete();
    }
}
