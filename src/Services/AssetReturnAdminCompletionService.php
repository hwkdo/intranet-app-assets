<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Illuminate\Support\Facades\DB;

class AssetReturnAdminCompletionService
{
    public const PENDING_SESSION_KEY = 'intranet_app_assets.return_complete_pending';

    public const ResolutionNewOwner = 'new_owner';

    public const ResolutionSetLocation = 'set_location';

    /**
     * @param  array{resolution: string, new_owner_user_id?: int|null, location?: string|null}  $data
     */
    public function complete(
        AssetReturn $assetReturn,
        int $adminUserId,
        string $resolution,
        ?int $newOwnerUserId,
        ?string $location,
        ?string $note = null,
    ): void {
        if ($assetReturn->isCompleted()) {
            throw new \InvalidArgumentException('Diese Rückgabe ist bereits abgeschlossen.');
        }

        $handover = $assetReturn->handover;
        if ($handover === null) {
            throw new \InvalidArgumentException('Zugehörige Übergabe fehlt.');
        }

        $asset = $handover->asset;
        if ($asset === null) {
            throw new \InvalidArgumentException('Asset fehlt.');
        }

        $returnId = $assetReturn->id;
        $formerHolderUserId = $handover->recipient_user_id;

        $note = $note !== null ? trim($note) : '';

        AssetAuditContext::runWith('assets.return.complete', function () use ($assetReturn, $asset, $adminUserId, $resolution, $newOwnerUserId, $location, $returnId, $formerHolderUserId, $note): void {
            DB::transaction(function () use ($assetReturn, $asset, $adminUserId, $resolution, $newOwnerUserId, $location, $returnId, $formerHolderUserId, $note): void {
                $this->deleteAllHandoversForAsset($asset);

                $assetReturn = AssetReturn::query()->findOrFail($returnId);

                $now = now();
                $assetReturn->update([
                    'recipient_user_id' => $adminUserId,
                    'received_confirmed_at' => $assetReturn->received_confirmed_at ?? $now,
                    'completed_at' => $now,
                ]);

                $baseMeta = [
                    'asset_return_id' => $returnId,
                    'former_holder_user_id' => $formerHolderUserId,
                    'resolution' => $resolution,
                    'bulk_note' => $note !== '' ? $note : null,
                ];

                match ($resolution) {
                    self::ResolutionNewOwner => $this->applyNewOwner($asset, $assetReturn, $adminUserId, $newOwnerUserId, $baseMeta, $note),
                    self::ResolutionSetLocation => $this->applySetLocation($asset, $assetReturn, $adminUserId, $location, $baseMeta, $note),
                    default => throw new \InvalidArgumentException('Unbekannte Auflösung.'),
                };
            });
        });
    }

    /**
     * @param  array<string, mixed>  $baseMeta
     */
    private function applyNewOwner(Asset $asset, AssetReturn $assetReturn, int $adminUserId, ?int $newOwnerUserId, array $baseMeta, string $note): void
    {
        if ($newOwnerUserId === null || $newOwnerUserId < 1) {
            throw new \InvalidArgumentException('Neuer Besitzer erforderlich.');
        }

        $asset->refresh();
        $asset->update([
            'user_id' => $newOwnerUserId,
            'is_missing' => false,
        ]);
        $asset->refresh();
        $asset->ensureHandoverForOwner();

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventReturnCompletedByAdmin,
            'user_id' => $adminUserId,
            'reason' => $note !== '' ? $note : 'Rückgabe: Empfang bestätigt, neuer Besitzer zugewiesen.',
            'meta' => array_merge($baseMeta, [
                'initiated_by_user_id' => $assetReturn->initiated_by_user_id,
                'new_owner_user_id' => $newOwnerUserId,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $baseMeta
     */
    private function applySetLocation(Asset $asset, AssetReturn $assetReturn, int $adminUserId, ?string $location, array $baseMeta, string $note): void
    {
        $location = $location !== null ? trim($location) : '';
        if ($location === '') {
            throw new \InvalidArgumentException('Standort erforderlich, wenn kein neuer Besitzer gewählt wird.');
        }

        $asset->refresh();
        $asset->update([
            'user_id' => null,
            'location' => $location,
            'is_missing' => false,
        ]);

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventReturnCompletedByAdmin,
            'user_id' => $adminUserId,
            'reason' => $note !== '' ? $note : 'Rückgabe: Empfang bestätigt, Besitzer entfernt, Standort gesetzt.',
            'meta' => array_merge($baseMeta, [
                'initiated_by_user_id' => $assetReturn->initiated_by_user_id,
                'location' => $location,
            ]),
        ]);
    }

    private function deleteAllHandoversForAsset(Asset $asset): void
    {
        Handover::query()
            ->where('asset_id', $asset->id)
            ->with('assetReturns')
            ->get()
            ->each(function (Handover $handover): void {
                $handover->notes()->delete();
                $handover->delete();
            });
    }
}
