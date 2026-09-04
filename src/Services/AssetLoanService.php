<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Hwkdo\IntranetAppAssets\Enums\AssetReturnSource;
use Hwkdo\IntranetAppAssets\Enums\ReturnScheduleType;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\AdminHandoverChannel;
use Hwkdo\IntranetAppAssets\Support\AdminLoanEligibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AssetLoanService
{
    public function __construct(
        private AdminAssistedHandoverService $handoverService,
        private ScheduledReturnReminderService $reminderService,
    ) {}

    /**
     * Startet eine Verleih-Übergabe inkl. Zieldatum (Rückgabe wird erst bei Bestätigung angelegt).
     *
     * @throws \InvalidArgumentException
     */
    public function startLoan(
        Asset $asset,
        int $adminUserId,
        int $recipientUserId,
        CarbonInterface $loanDueAt,
        string $channel,
        ?string $note = null,
    ): Handover {
        if (! AdminLoanEligibility::isEligible($asset)) {
            throw new \InvalidArgumentException('Dieses Asset kann derzeit nicht verliehen werden.');
        }

        if (! User::query()->aktiv()->whereKey($recipientUserId)->exists()) {
            throw new \InvalidArgumentException('Empfänger muss ein aktiver Mitarbeiter sein.');
        }

        $validationMessage = $this->reminderService->validateLoanDueAt($loanDueAt);
        if ($validationMessage !== null) {
            throw new \InvalidArgumentException($validationMessage);
        }

        if (! in_array($channel, AdminHandoverChannel::selectableValues(), true)) {
            throw new \InvalidArgumentException('Bitte einen verfügbaren Bestätigungsweg wählen.');
        }

        return DB::transaction(function () use ($asset, $adminUserId, $recipientUserId, $loanDueAt, $channel, $note): Handover {
            $handover = $this->handoverService->prepareOpenHandover(
                $asset->fresh() ?? $asset,
                $adminUserId,
                $recipientUserId,
            );

            $handover->update([
                'loan_due_at' => Carbon::instance($loanDueAt),
            ]);

            $this->handoverService->setPendingConfirmationChannel(
                $handover,
                $channel,
                $adminUserId,
            );

            $handover->refresh();

            $noteText = $note !== null ? trim($note) : '';
            if ($noteText !== '') {
                $handover->notes()->create([
                    'note' => 'Verleih eingeleitet:'."\n\n".$noteText,
                    'user_id' => $adminUserId,
                ]);
            }

            $asset = $handover->asset ?? $asset;
            $asset->historyEntries()->create([
                'event' => AssetHistory::EventAdminLoanStarted,
                'user_id' => $adminUserId,
                'reason' => 'Admin hat Verleih gestartet (Rückgabe bis '
                    .Carbon::instance($loanDueAt)->format('d.m.Y H:i')
                    .', Kanal: '.$this->channelLabel($channel).').'
                    .($noteText !== '' ? ' '.$noteText : ''),
                'meta' => [
                    'source' => 'assets.admin.loan.start',
                    'handover_id' => $handover->id,
                    'channel' => $channel,
                    'recipient_user_id' => $handover->recipient_user_id,
                    'loan_due_at' => Carbon::instance($loanDueAt)->toIso8601String(),
                ],
            ]);

            return $handover->fresh(['asset', 'recipient']) ?? $handover;
        });
    }

    /**
     * Legt nach Bestätigung einer Verleih-Übergabe die geplante Rückgabe an (idempotent).
     */
    public function ensureScheduledReturnAfterConfirm(Handover $handover): ?AssetReturn
    {
        $handover->refresh();

        if (! $handover->isLoan() || ! $handover->isConfirmed() || $handover->loan_due_at === null) {
            return null;
        }

        $existing = $handover->assetReturns()->whereNull('completed_at')->first();
        if ($existing !== null) {
            return $existing;
        }

        $return = AssetReturn::query()->create([
            'handover_id' => $handover->id,
            'initiated_by_user_id' => $handover->issuer_user_id ?? $handover->confirmed_assisted_by_user_id,
            'source' => AssetReturnSource::Loan,
            'schedule_type' => ReturnScheduleType::Scheduled,
            'scheduled_at' => $handover->loan_due_at,
        ]);

        $asset = $handover->asset;
        if ($asset instanceof Asset) {
            $asset->historyEntries()->create([
                'event' => AssetHistory::EventLoanReturnScheduledOnConfirm,
                'user_id' => $handover->confirmed_assisted_by_user_id ?? $handover->issuer_user_id ?? $handover->recipient_user_id,
                'reason' => 'Verleih bestätigt – geplante Rückgabe für '
                    .$handover->loan_due_at->format('d.m.Y H:i').' angelegt.',
                'meta' => [
                    'handover_id' => $handover->id,
                    'asset_return_id' => $return->id,
                    'loan_due_at' => $handover->loan_due_at->toIso8601String(),
                    'source' => AssetReturnSource::Loan->value,
                ],
            ]);
        }

        return $return;
    }

    private function channelLabel(string $channel): string
    {
        return match ($channel) {
            AdminHandoverChannel::PasswordNow => 'Passwort vor Ort',
            AdminHandoverChannel::SignopadZentrale => 'Signopad Zentrale',
            default => 'Empfänger bestätigt selbst',
        };
    }
}
