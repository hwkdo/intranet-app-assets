<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\Handover;

class RecipientHandoverConfirmationService
{
    public const METHOD_PASSWORD = 'password';

    public const METHOD_TOUCHSCREEN = 'touchscreen';

    public const METHOD_SIGNOPAD = 'signopad';

    /**
     * Bestätigt eine offene Übergabe für den Empfänger (gleiche Fachlogik wie Einzel-Flows).
     *
     * @throws \InvalidArgumentException wenn die Übergabe nicht bestätigt werden kann
     */
    public function confirmForRecipient(
        Handover $handover,
        int $recipientUserId,
        string $confirmationMethod,
        ?string $signatureBase64 = null,
    ): void {
        if ((int) $handover->recipient_user_id !== $recipientUserId) {
            throw new \InvalidArgumentException('Keine Berechtigung für diese Übergabe.');
        }
        if ($handover->isConfirmed()) {
            throw new \InvalidArgumentException('Übergabe wurde bereits bestätigt.');
        }
        if ($handover->isRejected()) {
            throw new \InvalidArgumentException('Übergabe wurde abgelehnt.');
        }

        $allowed = [self::METHOD_PASSWORD, self::METHOD_TOUCHSCREEN, self::METHOD_SIGNOPAD];
        if (! in_array($confirmationMethod, $allowed, true)) {
            throw new \InvalidArgumentException('Unbekannte Bestätigungsmethode.');
        }

        if ($confirmationMethod === self::METHOD_TOUCHSCREEN || $confirmationMethod === self::METHOD_SIGNOPAD) {
            if ($signatureBase64 === null || trim($signatureBase64) === '') {
                throw new \InvalidArgumentException('Unterschrift erforderlich.');
            }
        }

        $attributes = [
            'confirmed_at' => now(),
            'confirmation_method' => $confirmationMethod,
        ];
        if ($confirmationMethod === self::METHOD_TOUCHSCREEN || $confirmationMethod === self::METHOD_SIGNOPAD) {
            $attributes['signature'] = $signatureBase64;
        }
        $handover->update($attributes);

        $asset = $handover->asset;
        if ($asset instanceof Asset) {
            $this->clearAssetFlagsAfterConfirm($asset, $handover, $recipientUserId, $confirmationMethod);
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function rejectForRecipient(Handover $handover, int $recipientUserId, string $reason): void
    {
        if ((int) $handover->recipient_user_id !== $recipientUserId) {
            throw new \InvalidArgumentException('Keine Berechtigung für diese Übergabe.');
        }
        if ($handover->isConfirmed()) {
            throw new \InvalidArgumentException('Übergabe wurde bereits bestätigt.');
        }
        if ($handover->isRejected()) {
            throw new \InvalidArgumentException('Übergabe wurde bereits abgelehnt.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Begründung erforderlich.');
        }

        $handover->notes()->create([
            'note' => 'Übergabe abgelehnt — Begründung des Empfängers:'."\n\n".$reason,
            'user_id' => $recipientUserId,
        ]);

        $handover->update([
            'rejected_at' => now(),
            'rejected_by_user_id' => $recipientUserId,
        ]);

        $asset = $handover->asset;
        if ($asset instanceof Asset) {
            $asset->historyEntries()->create([
                'event' => AssetHistory::EventHandoverRejectedByRecipient,
                'user_id' => $recipientUserId,
                'reason' => $reason,
                'meta' => [
                    'handover_id' => $handover->id,
                    'recipient_user_id' => $handover->recipient_user_id,
                ],
            ]);
        }
    }

    private function clearAssetFlagsAfterConfirm(
        Asset $asset,
        Handover $handover,
        int $recipientUserId,
        string $confirmationMethod,
    ): void {
        $clearedFlags = [];
        if ($asset->is_clarification) {
            $clearedFlags[] = 'is_clarification';
        }
        if ($asset->is_missing) {
            $clearedFlags[] = 'is_missing';
        }

        $asset->update([
            'is_clarification' => false,
            'is_missing' => false,
        ]);

        if ($clearedFlags !== []) {
            $asset->historyEntries()->create([
                'event' => AssetHistory::EventHandoverConfirmedStatusCleared,
                'user_id' => $recipientUserId,
                'reason' => 'Bei Bestätigung der Übergabe wurden Status-Flags zurückgesetzt.',
                'meta' => [
                    'handover_id' => $handover->id,
                    'confirmation_method' => $confirmationMethod,
                    'cleared_flags' => $clearedFlags,
                ],
            ]);
        }
    }
}
