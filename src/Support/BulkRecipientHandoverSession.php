<?php

namespace Hwkdo\IntranetAppAssets\Support;

final class BulkRecipientHandoverSession
{
    public const CONFIRM_PENDING_KEY = 'intranet_app_assets.bulk_recipient_handover_confirm';

    public const REJECT_PENDING_KEY = 'intranet_app_assets.bulk_recipient_handover_reject';

    /** Session-Key für Checkbox-Auswahl auf „Meine Assets“ (Mehrfach-Übergaben). */
    public const MY_ASSETS_SELECTION_SESSION_KEY = 'intranet_app_assets.bulk.my_assets.selection';

    public static function forgetMyAssetsBulkSelection(): void
    {
        session()->forget(self::MY_ASSETS_SELECTION_SESSION_KEY);
    }

    /**
     * @param  list<int>  $handoverIds
     * @return array{recipient_user_id: int, handover_ids: list<int>}|null
     */
    public static function getConfirmPayload(): ?array
    {
        $raw = session()->get(self::CONFIRM_PENDING_KEY);
        if (! is_array($raw)) {
            return null;
        }
        $userId = (int) ($raw['recipient_user_id'] ?? 0);
        $ids = $raw['handover_ids'] ?? [];
        if ($userId < 1 || ! is_array($ids)) {
            return null;
        }
        $normalized = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($normalized === []) {
            return null;
        }

        return [
            'recipient_user_id' => $userId,
            'handover_ids' => $normalized,
        ];
    }

    /**
     * @param  list<int>  $handoverIds
     */
    public static function putConfirmPending(int $recipientUserId, array $handoverIds): void
    {
        session()->put(self::CONFIRM_PENDING_KEY, [
            'recipient_user_id' => $recipientUserId,
            'handover_ids' => array_values($handoverIds),
        ]);
    }

    public static function forgetConfirmPending(): void
    {
        session()->forget(self::CONFIRM_PENDING_KEY);
    }

    /**
     * @return array{recipient_user_id: int, handover_ids: list<int>, reason: string}|null
     */
    public static function getRejectPayload(): ?array
    {
        $raw = session()->get(self::REJECT_PENDING_KEY);
        if (! is_array($raw)) {
            return null;
        }
        $userId = (int) ($raw['recipient_user_id'] ?? 0);
        $ids = $raw['handover_ids'] ?? [];
        $reason = isset($raw['reason']) ? trim((string) $raw['reason']) : '';
        if ($userId < 1 || ! is_array($ids) || $reason === '') {
            return null;
        }
        $normalized = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($normalized === []) {
            return null;
        }

        return [
            'recipient_user_id' => $userId,
            'handover_ids' => $normalized,
            'reason' => $reason,
        ];
    }

    /**
     * @param  list<int>  $handoverIds
     */
    public static function putRejectPending(int $recipientUserId, array $handoverIds, string $reason): void
    {
        session()->put(self::REJECT_PENDING_KEY, [
            'recipient_user_id' => $recipientUserId,
            'handover_ids' => array_values($handoverIds),
            'reason' => $reason,
        ]);
    }

    public static function forgetRejectPending(): void
    {
        session()->forget(self::REJECT_PENDING_KEY);
    }
}
