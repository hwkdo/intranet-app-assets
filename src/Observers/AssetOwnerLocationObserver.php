<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Observers;

use Hwkdo\IntranetAppAssets\Models\Asset;

/**
 * Besitzer und Pool-Standort schließen sich fachlich aus: Sobald ein Besitzer gesetzt ist,
 * darf kein Intranet-Standort (location) mehr am Asset hängen.
 */
class AssetOwnerLocationObserver
{
    public function saving(Asset $asset): void
    {
        if ($asset->user_id === null) {
            return;
        }

        if (! filled($asset->location)) {
            return;
        }

        $asset->location = null;
    }
}
