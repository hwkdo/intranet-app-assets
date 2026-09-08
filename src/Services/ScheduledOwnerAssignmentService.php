<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\ScheduledOwnerAssignment;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ScheduledOwnerAssignmentService
{
    public function __construct(
        private HandoverSupersessionService $handoverSupersession,
    ) {}

    public function processDue(?CarbonInterface $now = null): int
    {
        $now = Carbon::instance($now ?? now());
        $processed = 0;

        $ids = ScheduledOwnerAssignment::query()
            ->pending()
            ->where('execute_at', '<=', $now)
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            $assignment = ScheduledOwnerAssignment::query()->find($id);
            if ($assignment === null) {
                continue;
            }

            if ($this->processAssignment($assignment)) {
                $processed++;
            }
        }

        return $processed;
    }

    public function processAssignment(ScheduledOwnerAssignment $assignment): bool
    {
        if ($assignment->isProcessed()) {
            return false;
        }

        $asset = $assignment->asset;
        if ($asset === null || (int) $assignment->new_user_id < 1) {
            $assignment->forceFill(['processed_at' => now()])->save();

            return false;
        }

        AssetAuditContext::runWith('assets.scheduled_owner_assignment', function () use ($assignment, $asset): void {
            DB::transaction(function () use ($assignment, $asset): void {
                $actorId = auth()->id();
                $actorId = is_int($actorId) ? $actorId : null;

                $this->handoverSupersession->supersedeAllActiveForAsset(
                    $asset,
                    $actorId,
                    'austritt:scheduled_owner_assignment',
                );

                $formerUserId = $asset->user_id;
                $newUserId = (int) $assignment->new_user_id;

                $asset->update([
                    'user_id' => $newUserId,
                    'is_missing' => false,
                    'is_clarification' => false,
                    'is_in_stock' => false,
                ]);

                $asset->historyEntries()->create([
                    'event' => AssetHistory::EventAustrittDisposition,
                    'user_id' => $actorId,
                    'reason' => 'Austritt: Geplanter Besitzerwechsel ausgeführt.',
                    'meta' => [
                        'source' => 'ma_austritt',
                        'choice' => 'werde_ich_erhalten',
                        'scheduled_owner_assignment_id' => $assignment->id,
                        'former_user_id' => $formerUserId,
                        'new_user_id' => $newUserId,
                        'flow_id' => $assignment->flow_id,
                    ],
                ]);

                $assignment->forceFill(['processed_at' => now()])->save();
            });
        });

        return true;
    }
}
