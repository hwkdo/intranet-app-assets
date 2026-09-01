<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Illuminate\Support\Facades\DB;

class HandoverRejectionAdminResolutionService
{
    public const PENDING_RESOLUTION_SESSION_KEY = 'intranet_app_assets.rejected_handover_resolve_pending';

    public const ResolutionNewOwner = 'new_owner';

    public const ResolutionSetLocation = 'set_location';

    public const ResolutionMarkMissing = 'mark_missing';

    /**
     * @param  array{resolution: string, new_owner_user_id?: int|null, location?: string|null}  $data
     */
    public function resolve(
        Handover $handover,
        int $adminUserId,
        string $resolution,
        ?int $newOwnerUserId,
        ?string $location,
        ?string $note = null,
    ): void {
        if (! $handover->isRejected() || $handover->isConfirmed() || $handover->isSuperseded()) {
            throw new \InvalidArgumentException('Übergabe kann nicht aufgelöst werden.');
        }

        $asset = $handover->asset;
        if ($asset === null) {
            throw new \InvalidArgumentException('Asset fehlt.');
        }

        $recipientId = $handover->recipient_user_id;
        $recipientName = $handover->recipient?->name;

        $note = $note !== null ? trim($note) : '';

        AssetAuditContext::runWith('assets.rejected_handover.resolve', function () use ($handover, $asset, $adminUserId, $resolution, $newOwnerUserId, $location, $recipientId, $recipientName, $note): void {
            DB::transaction(function () use ($handover, $asset, $adminUserId, $resolution, $newOwnerUserId, $location, $recipientId, $recipientName, $note): void {
                $baseMeta = [
                    'handover_id' => $handover->id,
                    'former_recipient_user_id' => $recipientId,
                    'former_recipient_name' => $recipientName,
                    'resolution' => $resolution,
                    'bulk_note' => $note !== '' ? $note : null,
                ];

                $asset->historyEntries()->create([
                    'event' => AssetHistory::EventHandoverRejectionAdminAcknowledged,
                    'user_id' => $adminUserId,
                    'reason' => 'Admin bestätigt: Das Asset liegt nicht beim zugewiesenen Benutzer.',
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

    /**
     * @param  array<string, mixed>  $baseMeta
     */
    private function applyNewOwner(Asset $asset, Handover $handover, int $adminUserId, ?int $newOwnerUserId, array $baseMeta, string $note): void
    {
        if ($newOwnerUserId === null || $newOwnerUserId < 1) {
            throw new \InvalidArgumentException('Neuer Besitzer erforderlich.');
        }

        app(HandoverSupersessionService::class)->supersede($handover, $adminUserId, 'rejected_handover:new_owner');

        $asset->update([
            'user_id' => $newOwnerUserId,
            'is_missing' => false,
            'is_in_stock' => false,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventHandoverRejectionAdminResolvedNewOwner,
            'user_id' => $adminUserId,
            'reason' => $note !== '' ? $note : 'Abgelehnte Übergabe: Neuer Besitzer zugewiesen.',
            'meta' => array_merge($baseMeta, [
                'new_owner_user_id' => $newOwnerUserId,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $baseMeta
     */
    private function applySetLocation(Asset $asset, Handover $handover, int $adminUserId, ?string $location, array $baseMeta, string $note): void
    {
        $location = $location !== null ? trim($location) : '';
        if ($location === '') {
            throw new \InvalidArgumentException('Standort erforderlich.');
        }

        app(HandoverSupersessionService::class)->supersede($handover, $adminUserId, 'rejected_handover:set_location');

        $asset->update([
            'user_id' => null,
            'location' => $location,
            'is_missing' => false,
            'is_in_stock' => true,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventHandoverRejectionAdminResolvedLocation,
            'user_id' => $adminUserId,
            'reason' => $note !== '' ? $note : 'Abgelehnte Übergabe: Besitzer entfernt, Standort gesetzt.',
            'meta' => array_merge($baseMeta, [
                'location' => $location,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $baseMeta
     */
    private function applyMarkMissing(Asset $asset, Handover $handover, int $adminUserId, array $baseMeta, string $note): void
    {
        app(HandoverSupersessionService::class)->supersede($handover, $adminUserId, 'rejected_handover:mark_missing');

        $asset->update([
            'user_id' => null,
            'is_missing' => true,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventHandoverRejectionAdminResolvedMissing,
            'user_id' => $adminUserId,
            'reason' => $note !== '' ? $note : 'Abgelehnte Übergabe: Als vermisst markiert, Besitzer entfernt.',
            'meta' => $baseMeta,
        ]);
    }
}
