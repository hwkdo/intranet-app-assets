<?php

namespace Hwkdo\IntranetAppAssets\Support;

final class BulkAdminWorkflowSession
{
    public const SESSION_KEY = 'intranet_app_assets.bulk_admin_workflow';

    public const FLOW_RETURN_COMPLETE = 'return_complete';

    public const FLOW_CLARIFICATION = 'clarification';

    public const FLOW_OPEN_HANDOVER = 'open_handover';

    public const FLOW_REJECTED_HANDOVER = 'rejected_handover';

    public const FLOW_RETURN_INITIATE = 'return_initiate';

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $payload
     */
    public static function put(string $flow, array $ids, array $payload): void
    {
        session()->put(self::SESSION_KEY, [
            'admin_user_id' => (int) auth()->id(),
            'flow' => $flow,
            'ids' => array_values($ids),
            'payload' => $payload,
        ]);
    }

    /**
     * @return array{admin_user_id: int, flow: string, ids: list<int>, payload: array<string, mixed>}|null
     */
    public static function getValidated(): ?array
    {
        $raw = session()->get(self::SESSION_KEY);
        if (! is_array($raw)) {
            return null;
        }
        $adminId = (int) ($raw['admin_user_id'] ?? 0);
        $flow = (string) ($raw['flow'] ?? '');
        $ids = $raw['ids'] ?? [];
        $payload = $raw['payload'] ?? null;
        if ($adminId < 1 || $flow === '' || ! is_array($ids) || ! is_array($payload)) {
            return null;
        }
        if ((int) auth()->id() !== $adminId) {
            return null;
        }
        $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($normalizedIds === []) {
            return null;
        }

        return [
            'admin_user_id' => $adminId,
            'flow' => $flow,
            'ids' => $normalizedIds,
            'payload' => $payload,
        ];
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Leert die persistente Mehrfachauswahl der jeweiligen Übersicht nach erfolgreicher Bulk-Ausführung.
     */
    public static function forgetSelectionAfterBulkSuccess(string $flow): void
    {
        $key = match ($flow) {
            self::FLOW_RETURN_COMPLETE => 'intranet_app_assets.bulk.pending_returns.selection',
            self::FLOW_CLARIFICATION => 'intranet_app_assets.bulk.clarifications.selection',
            self::FLOW_OPEN_HANDOVER => 'intranet_app_assets.bulk.open_handovers.selection',
            self::FLOW_REJECTED_HANDOVER => 'intranet_app_assets.bulk.rejected_handovers.selection',
            self::FLOW_RETURN_INITIATE => 'intranet_app_assets.bulk.assets_list.selection',
            default => null,
        };

        if ($key !== null) {
            session()->forget($key);
        }
    }
}
