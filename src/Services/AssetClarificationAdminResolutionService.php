<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Illuminate\Support\Facades\DB;

class AssetClarificationAdminResolutionService
{
    public const PENDING_RESOLUTION_SESSION_KEY = 'intranet_app_assets.clarification_resolve_pending';

    public const ResolutionClearOnly = 'clear_only';

    public const ResolutionNewOwner = 'new_owner';

    public const ResolutionSetLocation = 'set_location';

    public const ResolutionMarkMissing = 'mark_missing';

    /**
     * @param  array{resolution: string, new_owner_user_id?: int|null, location?: string|null}  $data
     */
    public function resolve(
        Asset $asset,
        int $adminUserId,
        string $resolution,
        ?int $newOwnerUserId,
        ?string $location,
        ?string $note = null,
        bool $markInStock = true,
    ): void {
        if (! $asset->is_clarification) {
            throw new \InvalidArgumentException('Dieses Asset ist nicht in Klärung.');
        }

        $note = $note !== null ? trim($note) : '';

        AssetAuditContext::runWith('assets.clarification.resolve', function () use ($asset, $adminUserId, $resolution, $newOwnerUserId, $location, $note, $markInStock): void {
            DB::transaction(function () use ($asset, $adminUserId, $resolution, $newOwnerUserId, $location, $note, $markInStock): void {
                $baseMeta = [
                    'resolution' => $resolution,
                    'former_user_id' => $asset->user_id,
                    'bulk_note' => $note !== '' ? $note : null,
                ];

                match ($resolution) {
                    self::ResolutionClearOnly => $this->applyClearOnly($asset, $adminUserId, $baseMeta, $note),
                    self::ResolutionNewOwner => $this->applyNewOwner($asset, $adminUserId, $newOwnerUserId, $baseMeta, $note),
                    self::ResolutionSetLocation => $this->applySetLocation($asset, $adminUserId, $location, $baseMeta, $note, $markInStock),
                    self::ResolutionMarkMissing => $this->applyMarkMissing($asset, $adminUserId, $baseMeta, $note),
                    default => throw new \InvalidArgumentException('Unbekannte Auflösung.'),
                };
            });
        });
    }

    /**
     * @param  array<string, mixed>  $baseMeta
     */
    private function applyClearOnly(Asset $asset, int $adminUserId, array $baseMeta, string $note): void
    {
        $asset->update([
            'is_clarification' => false,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventClarificationAdminResolvedCleared,
            'user_id' => $adminUserId,
            'reason' => $note !== '' ? $note : 'Klärung: Keine Änderung am Asset, Flag wurde entfernt.',
            'meta' => $baseMeta,
        ]);
    }

    /**
     * @param  array<string, mixed>  $baseMeta
     */
    private function applyNewOwner(Asset $asset, int $adminUserId, ?int $newOwnerUserId, array $baseMeta, string $note): void
    {
        if ($newOwnerUserId === null || $newOwnerUserId < 1) {
            throw new \InvalidArgumentException('Neuer Besitzer erforderlich.');
        }

        app(HandoverSupersessionService::class)->supersedeAllActiveForAsset(
            $asset,
            $adminUserId,
            'clarification:new_owner',
        );

        $asset->update([
            'user_id' => $newOwnerUserId,
            'is_clarification' => false,
            'is_missing' => false,
            'is_in_stock' => false,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventClarificationAdminResolvedNewOwner,
            'user_id' => $adminUserId,
            'reason' => $note !== '' ? $note : 'Klärung: Neuer Besitzer zugewiesen.',
            'meta' => array_merge($baseMeta, [
                'new_owner_user_id' => $newOwnerUserId,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $baseMeta
     */
    private function applySetLocation(
        Asset $asset,
        int $adminUserId,
        ?string $location,
        array $baseMeta,
        string $note,
        bool $markInStock,
    ): void {
        $location = $location !== null ? trim($location) : '';
        if ($location === '') {
            throw new \InvalidArgumentException('Standort erforderlich.');
        }

        app(HandoverSupersessionService::class)->supersedeAllActiveForAsset(
            $asset,
            $adminUserId,
            'clarification:set_location',
        );

        $asset->update([
            'user_id' => null,
            'location' => $location,
            'is_clarification' => false,
            'is_missing' => false,
            'is_in_stock' => $markInStock,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventClarificationAdminResolvedLocation,
            'user_id' => $adminUserId,
            'reason' => $note !== '' ? $note : 'Klärung: Besitzer entfernt, Standort gesetzt.',
            'meta' => array_merge($baseMeta, [
                'location' => $location,
                'mark_in_stock' => $markInStock,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $baseMeta
     */
    private function applyMarkMissing(Asset $asset, int $adminUserId, array $baseMeta, string $note): void
    {
        app(HandoverSupersessionService::class)->supersedeAllActiveForAsset(
            $asset,
            $adminUserId,
            'clarification:mark_missing',
        );

        $asset->update([
            'user_id' => null,
            'is_clarification' => false,
            'is_missing' => true,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventClarificationAdminResolvedMissing,
            'user_id' => $adminUserId,
            'reason' => $note !== '' ? $note : 'Klärung: Als vermisst markiert, Besitzer entfernt.',
            'meta' => $baseMeta,
        ]);
    }

}
