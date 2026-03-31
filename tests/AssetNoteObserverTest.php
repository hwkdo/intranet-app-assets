<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetAttachment;
use Hwkdo\IntranetAppAssets\Models\AssetNote;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Observers\AssetNoteObserver;

it('reindexes asset when note belongs directly to asset', function () {
    $asset = new class extends Asset
    {
        public bool $searchableCalled = false;

        public function searchable()
        {
            $this->searchableCalled = true;

            return null;
        }
    };

    $note = new AssetNote();
    $note->setRelation('noteable', $asset);

    (new AssetNoteObserver())->created($note);

    expect($asset->searchableCalled)->toBeTrue();
});

it('reindexes asset when note belongs to handover, return or attachment', function (string $type) {
    $asset = new class extends Asset
    {
        public bool $searchableCalled = false;

        public function searchable()
        {
            $this->searchableCalled = true;

            return null;
        }
    };

    $note = new AssetNote();

    if ($type === 'handover') {
        $handover = new Handover();
        $handover->setRelation('asset', $asset);
        $note->setRelation('noteable', $handover);
    }

    if ($type === 'assetReturn') {
        $handover = new Handover();
        $handover->setRelation('asset', $asset);

        $assetReturn = new AssetReturn();
        $assetReturn->setRelation('handover', $handover);
        $note->setRelation('noteable', $assetReturn);
    }

    if ($type === 'attachment') {
        $attachment = new AssetAttachment();
        $attachment->setRelation('asset', $asset);
        $note->setRelation('noteable', $attachment);
    }

    (new AssetNoteObserver())->updated($note);

    expect($asset->searchableCalled)->toBeTrue();
})->with(['handover', 'assetReturn', 'attachment']);
