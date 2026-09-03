<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Illuminate\Support\Facades\DB;

class AssetMissingAdminResolutionService
{
    public const PENDING_RESOLUTION_SESSION_KEY = 'intranet_app_assets.missing_resolve_pending';

    public const ResolutionClearOnly = 'found_clear_only';

    public const ResolutionNewOwner = 'found_new_owner';

    public const ResolutionSetLocation = 'found_set_location';

    public function resolve(
        Asset $asset,
        int $adminUserId,
        string $resolution,
        ?int $newOwnerUserId,
        ?string $location,
        ?string $note = null,
        bool $markInStock = true,
    ): void {
        if (! $asset->is_missing) {
            throw new \InvalidArgumentException('Dieses Asset ist nicht als vermisst markiert.');
        }

        $note = $note !== null ? trim($note) : '';
        if ($note === '' || mb_strlen($note) < 3) {
            throw new \InvalidArgumentException('Eine Dokumentation (mindestens 3 Zeichen) ist erforderlich.');
        }

        AssetAuditContext::runWith('assets.missing.resolve', function () use ($asset, $adminUserId, $resolution, $newOwnerUserId, $location, $note, $markInStock): void {
            DB::transaction(function () use ($asset, $adminUserId, $resolution, $newOwnerUserId, $location, $note, $markInStock): void {
                $baseMeta = [
                    'resolution' => $resolution,
                    'former_user_id' => $asset->user_id,
                    'former_location' => $asset->location,
                    'note' => $note,
                ];

                match ($resolution) {
                    self::ResolutionClearOnly => $this->applyClearOnly($asset, $adminUserId, $baseMeta, $note),
                    self::ResolutionNewOwner => $this->applyNewOwner($asset, $adminUserId, $newOwnerUserId, $baseMeta, $note),
                    self::ResolutionSetLocation => $this->applySetLocation($asset, $adminUserId, $location, $baseMeta, $note, $markInStock),
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
            'is_missing' => false,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventMissingAdminResolvedClearOnly,
            'user_id' => $adminUserId,
            'reason' => $note,
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
            'missing:new_owner',
        );

        $asset->update([
            'user_id' => $newOwnerUserId,
            'is_missing' => false,
            'is_in_stock' => false,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventMissingAdminResolvedNewOwner,
            'user_id' => $adminUserId,
            'reason' => $note,
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
            'missing:set_location',
        );

        $asset->update([
            'user_id' => null,
            'location' => $location,
            'is_missing' => false,
            'is_in_stock' => $markInStock,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventMissingAdminResolvedLocation,
            'user_id' => $adminUserId,
            'reason' => $note,
            'meta' => array_merge($baseMeta, [
                'location' => $location,
                'mark_in_stock' => $markInStock,
            ]),
        ]);
    }
}
