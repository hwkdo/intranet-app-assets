<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\BulkAdminWorkflowSession;
use Illuminate\Support\Facades\DB;

class BulkAdminWorkflowExecutor
{
    /**
     * @param  array{admin_user_id: int, flow: string, ids: list<int>, payload: array<string, mixed>}  $session
     * @return array{processed: int, failed: int}
     */
    public function execute(array $session): array
    {
        return match ($session['flow']) {
            BulkAdminWorkflowSession::FLOW_RETURN_COMPLETE => $this->executeReturnComplete($session),
            BulkAdminWorkflowSession::FLOW_CLARIFICATION => $this->executeClarification($session),
            BulkAdminWorkflowSession::FLOW_OPEN_HANDOVER => $this->executeOpenHandover($session),
            BulkAdminWorkflowSession::FLOW_REJECTED_HANDOVER => $this->executeRejectedHandover($session),
            BulkAdminWorkflowSession::FLOW_RETURN_INITIATE => $this->executeReturnInitiate($session),
            default => throw new \InvalidArgumentException('Unbekannter Mehrfachaktions-Typ.'),
        };
    }

    /**
     * @param  array{admin_user_id: int, ids: list<int>, payload: array<string, mixed>}  $session
     * @return array{processed: int, failed: int}
     */
    private function executeReturnComplete(array $session): array
    {
        $adminId = $session['admin_user_id'];
        $p = $session['payload'];
        $resolution = (string) ($p['resolution'] ?? '');
        $newOwner = isset($p['new_owner_user_id']) ? (int) $p['new_owner_user_id'] : null;
        $location = isset($p['location']) ? trim((string) $p['location']) : null;
        $reason = trim((string) ($p['bulk_reason'] ?? ''));

        $service = app(AssetReturnAdminCompletionService::class);
        $processed = 0;
        $failed = 0;

        $returns = AssetReturn::query()
            ->whereIn('id', $session['ids'])
            ->whereNull('completed_at')
            ->get()
            ->keyBy('id');

        foreach ($session['ids'] as $id) {
            $assetReturn = $returns->get($id);
            if ($assetReturn === null) {
                $failed++;
                continue;
            }
            try {
                $service->complete(
                    $assetReturn,
                    $adminId,
                    $resolution,
                    $resolution === AssetReturnAdminCompletionService::ResolutionNewOwner ? $newOwner : null,
                    $resolution === AssetReturnAdminCompletionService::ResolutionSetLocation ? $location : null,
                    $reason,
                );
                $processed++;
            } catch (\InvalidArgumentException) {
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /**
     * @param  array{admin_user_id: int, ids: list<int>, payload: array<string, mixed>}  $session
     * @return array{processed: int, failed: int}
     */
    private function executeClarification(array $session): array
    {
        $adminId = $session['admin_user_id'];
        $p = $session['payload'];
        $resolution = (string) ($p['resolution'] ?? '');
        $newOwner = isset($p['new_owner_user_id']) ? (int) $p['new_owner_user_id'] : null;
        $location = isset($p['location']) ? trim((string) $p['location']) : null;
        $reason = trim((string) ($p['bulk_reason'] ?? ''));

        $service = app(AssetClarificationAdminResolutionService::class);
        $processed = 0;
        $failed = 0;

        $assets = Asset::query()
            ->whereIn('id', $session['ids'])
            ->where('is_clarification', true)
            ->get()
            ->keyBy('id');

        foreach ($session['ids'] as $id) {
            $asset = $assets->get($id);
            if ($asset === null) {
                $failed++;
                continue;
            }
            try {
                $service->resolve(
                    $asset,
                    $adminId,
                    $resolution,
                    $resolution === AssetClarificationAdminResolutionService::ResolutionNewOwner ? $newOwner : null,
                    $resolution === AssetClarificationAdminResolutionService::ResolutionSetLocation ? $location : null,
                    $reason,
                );
                $processed++;
            } catch (\InvalidArgumentException) {
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /**
     * @param  array{admin_user_id: int, ids: list<int>, payload: array<string, mixed>}  $session
     * @return array{processed: int, failed: int}
     */
    private function executeOpenHandover(array $session): array
    {
        $adminId = $session['admin_user_id'];
        $p = $session['payload'];
        $resolution = (string) ($p['resolution'] ?? '');
        $newOwner = isset($p['new_owner_user_id']) ? (int) $p['new_owner_user_id'] : null;
        $location = isset($p['location']) ? trim((string) $p['location']) : null;
        $reason = trim((string) ($p['bulk_reason'] ?? ''));

        $service = app(OpenHandoverAdminResolutionService::class);
        $processed = 0;
        $failed = 0;

        $handovers = Handover::query()
            ->whereIn('id', $session['ids'])
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
            ->get()
            ->keyBy('id');

        foreach ($session['ids'] as $id) {
            $handover = $handovers->get($id);
            if ($handover === null) {
                $failed++;
                continue;
            }
            try {
                $service->resolve(
                    $handover,
                    $adminId,
                    $resolution,
                    $resolution === OpenHandoverAdminResolutionService::ResolutionNewOwner ? $newOwner : null,
                    $resolution === OpenHandoverAdminResolutionService::ResolutionSetLocation ? $location : null,
                    $reason,
                );
                $processed++;
            } catch (\InvalidArgumentException) {
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /**
     * @param  array{admin_user_id: int, ids: list<int>, payload: array<string, mixed>}  $session
     * @return array{processed: int, failed: int}
     */
    private function executeRejectedHandover(array $session): array
    {
        $adminId = $session['admin_user_id'];
        $p = $session['payload'];
        $resolution = (string) ($p['resolution'] ?? '');
        $newOwner = isset($p['new_owner_user_id']) ? (int) $p['new_owner_user_id'] : null;
        $location = isset($p['location']) ? trim((string) $p['location']) : null;
        $reason = trim((string) ($p['bulk_reason'] ?? ''));

        $service = app(HandoverRejectionAdminResolutionService::class);
        $processed = 0;
        $failed = 0;

        $handovers = Handover::query()
            ->whereIn('id', $session['ids'])
            ->rejectedPendingAdmin()
            ->get()
            ->keyBy('id');

        foreach ($session['ids'] as $id) {
            $handover = $handovers->get($id);
            if ($handover === null) {
                $failed++;
                continue;
            }
            try {
                $service->resolve(
                    $handover,
                    $adminId,
                    $resolution,
                    $resolution === HandoverRejectionAdminResolutionService::ResolutionNewOwner ? $newOwner : null,
                    $resolution === HandoverRejectionAdminResolutionService::ResolutionSetLocation ? $location : null,
                    $reason,
                );
                $processed++;
            } catch (\InvalidArgumentException) {
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /**
     * @param  array{admin_user_id: int, ids: list<int>, payload: array<string, mixed>}  $session
     * @return array{processed: int, failed: int}
     */
    private function executeReturnInitiate(array $session): array
    {
        $adminId = $session['admin_user_id'];
        $reason = trim((string) ($session['payload']['bulk_reason'] ?? ''));
        $processed = 0;
        $failed = 0;

        $handovers = Handover::query()
            ->whereIn('asset_id', $session['ids'])
            ->whereNotNull('confirmed_at')
            ->whereNull('rejected_at')
            ->whereDoesntHave('assetReturns', fn ($q) => $q->whereNull('completed_at'))
            ->with('asset')
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->get()
            ->unique('asset_id')
            ->keyBy('asset_id');

        foreach ($session['ids'] as $assetId) {
            $handover = $handovers->get($assetId);
            if ($handover === null) {
                $failed++;
                continue;
            }

            try {
                DB::transaction(function () use ($handover, $adminId, $reason): void {
                    $return = AssetReturn::query()->create([
                        'handover_id' => $handover->id,
                        'initiated_by_user_id' => $adminId,
                    ]);

                    $return->notes()->create([
                        'note' => 'Rückgabe eingeleitet (Mehrfachaktion):'."\n\n".$reason,
                        'user_id' => $adminId,
                    ]);

                    $asset = $handover->asset;
                    if ($asset !== null) {
                        $asset->historyEntries()->create([
                            'event' => AssetHistory::EventReturnInitiatedByHolder,
                            'user_id' => $adminId,
                            'reason' => $reason,
                            'meta' => [
                                'asset_return_id' => $return->id,
                                'handover_id' => $handover->id,
                                'initiated_by_admin' => true,
                                'is_bulk' => true,
                            ],
                        ]);
                    }
                });
                $processed++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }
}
