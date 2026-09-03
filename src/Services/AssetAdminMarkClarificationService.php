<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Illuminate\Support\Facades\DB;

class AssetAdminMarkClarificationService
{
    public function mark(Asset $asset, int $adminUserId, ?string $note = null): void
    {
        if ($asset->trashed()) {
            throw new \InvalidArgumentException('Gelöschte Assets können nicht in Klärung gesetzt werden.');
        }

        if ($asset->is_clarification) {
            throw new \InvalidArgumentException('Dieses Asset ist bereits in Klärung.');
        }

        $formerUserId = $asset->user_id;
        $note = $note !== null ? trim($note) : '';

        AssetAuditContext::runWith('assets.clarification.admin_mark', function () use ($asset, $adminUserId, $formerUserId, $note): void {
            DB::transaction(function () use ($asset, $adminUserId, $formerUserId, $note): void {
                $asset->update([
                    'is_clarification' => true,
                ]);

                $asset->historyEntries()->create([
                    'event' => AssetHistory::EventAdminMarkedClarification,
                    'user_id' => $adminUserId,
                    'reason' => $note !== ''
                        ? $note
                        : 'Admin hat das Asset als „In Klärung“ markiert.',
                    'meta' => [
                        'asset_id' => $asset->id,
                        'former_user_id' => $formerUserId,
                    ],
                ]);
            });
        });
    }
}
