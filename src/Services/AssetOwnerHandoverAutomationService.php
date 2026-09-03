<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;

class AssetOwnerHandoverAutomationService
{
    public function __construct(
        private HandoverSupersessionService $handoverSupersession,
    ) {}

    /**
     * Neue offene Übergabe nach Erstanlage mit Besitzer oder nach Besitzerwechsel / erster Zuweisung.
     */
    public function createHandoverForOwnerAssignment(Asset $asset): void
    {
        if ($asset->user_id === null) {
            return;
        }

        $actorId = auth()->id() !== null ? (int) auth()->id() : null;

        $this->handoverSupersession->supersedeConfirmedAndRejectedForAsset(
            $asset,
            $actorId,
            'new_owner_handover_cycle',
        );

        Handover::query()->create([
            'asset_id' => $asset->id,
            'recipient_user_id' => $asset->user_id,
            'issuer_user_id' => $actorId,
            'confirmed_at' => null,
            'confirmation_method' => null,
        ]);
    }

    /**
     * Backfill / Konsistenz: mindestens eine Übergabe je Asset + aktuellem Empfänger (beliebiger Status).
     */
    public function ensureAnyHandoverForCurrentOwner(Asset $asset): void
    {
        if ($asset->user_id === null) {
            return;
        }

        $actorId = auth()->id() !== null ? (int) auth()->id() : null;

        Handover::query()
            ->where('asset_id', $asset->id)
            ->active()
            ->where(function ($query) use ($asset): void {
                $query
                    ->whereNull('recipient_user_id')
                    ->orWhere('recipient_user_id', '!=', $asset->user_id);
            })
            ->get()
            ->each(fn (Handover $handover): mixed => $this->handoverSupersession->supersede(
                $handover,
                $actorId,
                'owner_changed_stale_handover',
            ));

        $exists = Handover::query()
            ->where('asset_id', $asset->id)
            ->where('recipient_user_id', $asset->user_id)
            ->active()
            ->exists();

        if ($exists) {
            return;
        }

        Handover::query()->create([
            'asset_id' => $asset->id,
            'recipient_user_id' => $asset->user_id,
            'issuer_user_id' => null,
            'confirmed_at' => null,
            'confirmation_method' => null,
        ]);
    }
}
