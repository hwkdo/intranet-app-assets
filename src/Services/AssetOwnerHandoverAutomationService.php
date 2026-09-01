<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;

class AssetOwnerHandoverAutomationService
{
    /**
     * Neue offene Übergabe nach Erstanlage mit Besitzer oder nach Besitzerwechsel / erster Zuweisung.
     */
    public function createHandoverForOwnerAssignment(Asset $asset): void
    {
        if ($asset->user_id === null) {
            return;
        }

        Handover::query()->create([
            'asset_id' => $asset->id,
            'recipient_user_id' => $asset->user_id,
            'issuer_user_id' => auth()->id(),
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
