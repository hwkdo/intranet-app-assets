<?php

namespace Hwkdo\IntranetAppAssets\Observers;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetAttachment;
use Hwkdo\IntranetAppAssets\Models\AssetNote;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;

class AssetNoteObserver
{
    public function created(AssetNote $assetNote): void
    {
        $this->reindexRelatedAsset($assetNote);
    }

    public function updated(AssetNote $assetNote): void
    {
        $this->reindexRelatedAsset($assetNote);
    }

    public function deleted(AssetNote $assetNote): void
    {
        $this->reindexRelatedAsset($assetNote);
    }

    private function reindexRelatedAsset(AssetNote $assetNote): void
    {
        $noteable = $assetNote->noteable;
        if ($noteable === null) {
            return;
        }

        $asset = match (true) {
            $noteable instanceof Asset => $noteable,
            $noteable instanceof Handover => $noteable->asset,
            $noteable instanceof AssetReturn => $noteable->handover?->asset,
            $noteable instanceof AssetAttachment => $noteable->asset,
            default => null,
        };

        $asset?->searchable();
    }
}
