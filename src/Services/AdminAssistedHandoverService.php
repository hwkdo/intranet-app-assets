<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Contracts\LdapPasswordVerifierInterface;
use Hwkdo\IntranetAppAssets\Events\AssetsZentraleHandoverQueueChanged;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\AdminHandoverChannel;
use Hwkdo\IntranetAppAssets\Support\AdminHandoverEligibility;
use Hwkdo\IntranetAppAssets\Support\AssetAuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminAssistedHandoverService
{
    public function __construct(
        private LdapPasswordVerifierInterface $ldapPasswordVerifier,
        private RecipientHandoverConfirmationService $confirmationService,
        private AssetOwnerHandoverAutomationService $handoverAutomation,
    ) {}

    /**
     * Weist ggf. den Empfänger zu und stellt eine offene Übergabe sicher.
     *
     * @throws \InvalidArgumentException
     */
    public function prepareOpenHandover(Asset $asset, int $adminUserId, ?int $recipientUserId): Handover
    {
        if (! AdminHandoverEligibility::isEligible($asset)) {
            throw new \InvalidArgumentException('Dieses Asset kann derzeit nicht übergeben werden.');
        }

        return DB::transaction(function () use ($asset, $adminUserId, $recipientUserId): Handover {
            $asset->refresh();

            if ($asset->user_id === null) {
                if ($recipientUserId === null || $recipientUserId <= 0) {
                    throw new \InvalidArgumentException('Bitte einen Empfänger wählen.');
                }

                if (! User::query()->whereKey($recipientUserId)->exists()) {
                    throw new \InvalidArgumentException('Empfänger nicht gefunden.');
                }

                AssetAuditContext::runWith('assets.admin.handover.prepare', function () use ($asset, $recipientUserId): void {
                    $asset->update([
                        'user_id' => $recipientUserId,
                        'is_in_stock' => false,
                        'location' => null,
                    ]);
                });

                $asset->refresh();
            }

            app(HandoverSupersessionService::class)->supersedeConfirmedAndRejectedForAsset(
                $asset,
                $adminUserId,
                'admin_assisted_handover_prepare',
            );

            $open = Handover::query()
                ->where('asset_id', $asset->id)
                ->where('recipient_user_id', $asset->user_id)
                ->open()
                ->orderByDesc('id')
                ->first();

            if ($open !== null) {
                if ($open->issuer_user_id === null) {
                    $open->update(['issuer_user_id' => $adminUserId]);
                }

                return $open->fresh(['asset', 'recipient']) ?? $open;
            }

            $this->handoverAutomation->createHandoverForOwnerAssignment($asset);

            $created = Handover::query()
                ->where('asset_id', $asset->id)
                ->where('recipient_user_id', $asset->user_id)
                ->open()
                ->orderByDesc('id')
                ->first();

            if ($created === null) {
                throw new \InvalidArgumentException('Offene Übergabe konnte nicht erzeugt werden.');
            }

            return $created->load(['asset', 'recipient']);
        });
    }

    /**
     * @throws ValidationException
     * @throws \InvalidArgumentException
     */
    public function confirmWithRecipientPassword(
        Handover $handover,
        int $adminUserId,
        string $recipientPassword,
    ): void {
        $handover->loadMissing(['recipient', 'asset']);

        $recipient = $handover->recipient;
        if (! $recipient instanceof User) {
            throw new \InvalidArgumentException('Empfänger der Übergabe fehlt.');
        }

        if ((int) $adminUserId === (int) $recipient->id) {
            throw new \InvalidArgumentException('Bitte die Empfänger-Bestätigung über den normalen Weg nutzen.');
        }

        if (! $this->ldapPasswordVerifier->verify($recipient, $recipientPassword)) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $this->confirmationService->confirmForRecipient(
            $handover,
            (int) $recipient->id,
            RecipientHandoverConfirmationService::METHOD_PASSWORD,
            null,
            $adminUserId,
        );

        $asset = $handover->asset;
        if ($asset instanceof Asset) {
            $asset->historyEntries()->create([
                'event' => AssetHistory::EventHandoverConfirmedAssistedByAdmin,
                'user_id' => $adminUserId,
                'reason' => 'Übergabe vor Ort per Empfänger-Passwort bestätigt (Admin-Assistent).',
                'meta' => [
                    'handover_id' => $handover->id,
                    'recipient_user_id' => $recipient->id,
                    'assisted_by_admin_id' => $adminUserId,
                    'channel' => AdminHandoverChannel::PasswordNow,
                    'confirmation_method' => RecipientHandoverConfirmationService::METHOD_PASSWORD,
                ],
            ]);
        }
    }

    /**
     * Merkt den gewünschten Bestätigungskanal an der offenen Übergabe (z. B. Signopad Zentrale).
     */
    public function setPendingConfirmationChannel(
        Handover $handover,
        string $channel,
        int $adminUserId,
    ): void {
        if (! in_array($channel, AdminHandoverChannel::allValues(), true)) {
            throw new \InvalidArgumentException('Unbekannter Bestätigungskanal.');
        }

        $attributes = [
            'pending_confirmation_channel' => $channel,
        ];

        if ($handover->issuer_user_id === null) {
            $attributes['issuer_user_id'] = $adminUserId;
        }

        $handover->update($attributes);

        if ($channel === AdminHandoverChannel::SignopadZentrale) {
            AssetsZentraleHandoverQueueChanged::dispatch(
                $handover->fresh() ?? $handover,
                AssetsZentraleHandoverQueueChanged::ACTION_QUEUED,
            );
        }
    }

    /**
     * Bestätigt eine an der Zentrale wartende Übergabe per Signopad (Empfänger unterschreibt, Zentrale assistiert).
     *
     * @throws \InvalidArgumentException
     */
    public function confirmAtZentraleWithSignopad(
        Handover $handover,
        int $zentraleUserId,
        string $signatureBase64,
    ): void {
        $handover->loadMissing(['recipient', 'asset']);

        if ($handover->pending_confirmation_channel !== AdminHandoverChannel::SignopadZentrale) {
            throw new \InvalidArgumentException('Diese Übergabe wartet nicht auf Signopad an der Zentrale.');
        }

        $recipient = $handover->recipient;
        if (! $recipient instanceof User) {
            throw new \InvalidArgumentException('Empfänger der Übergabe fehlt.');
        }

        if ((int) $zentraleUserId === (int) $recipient->id) {
            throw new \InvalidArgumentException('Bitte die Empfänger-Bestätigung über den normalen Weg nutzen.');
        }

        $this->confirmationService->confirmForRecipient(
            $handover,
            (int) $recipient->id,
            RecipientHandoverConfirmationService::METHOD_SIGNOPAD,
            $signatureBase64,
            $zentraleUserId,
        );

        $asset = $handover->asset;
        if ($asset instanceof Asset) {
            $asset->historyEntries()->create([
                'event' => AssetHistory::EventHandoverConfirmedAssistedByAdmin,
                'user_id' => $zentraleUserId,
                'reason' => 'Übergabe an der Zentrale per Signopad bestätigt.',
                'meta' => [
                    'handover_id' => $handover->id,
                    'recipient_user_id' => $recipient->id,
                    'assisted_by_admin_id' => $zentraleUserId,
                    'channel' => AdminHandoverChannel::SignopadZentrale,
                    'confirmation_method' => RecipientHandoverConfirmationService::METHOD_SIGNOPAD,
                ],
            ]);
        }
    }
}
