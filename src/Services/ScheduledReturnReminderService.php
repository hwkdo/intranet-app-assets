<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Enums\ReturnReminderPhase;
use Hwkdo\IntranetAppAssets\Enums\ReturnScheduleType;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Notifications\ReturnReminderNotification;
use Illuminate\Support\Carbon;

final class ScheduledReturnReminderService
{
    public function processDueReminders(?CarbonInterface $now = null): int
    {
        $now = Carbon::instance($now ?? now());
        $settings = $this->settings();
        $sent = 0;

        AssetReturn::query()
            ->scheduled()
            ->open()
            ->whereHas('handover.asset')
            ->orderBy('id')
            ->chunkById(100, function ($returns) use ($now, $settings, &$sent): void {
                foreach ($returns as $return) {
                    if ($this->processReturn($return, $now, $settings)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    public function processReturn(AssetReturn $return, CarbonInterface $now, ?AppSettings $settings = null): bool
    {
        $settings ??= $this->settings();

        if (! $return->isScheduled() || $return->isCompleted() || $return->scheduled_at === null) {
            return false;
        }

        $owner = $return->currentOwner();
        if (! $owner instanceof User) {
            return false;
        }

        $scheduledAt = Carbon::instance($return->scheduled_at);
        $reminder1At = $scheduledAt->copy()->subHours($settings->returnReminder1Hours);
        $reminder2At = $scheduledAt->copy()->subHours($settings->returnReminder2Hours);

        if ($now->greaterThanOrEqualTo($scheduledAt)) {
            if ($return->last_overdue_reminder_sent_at === null
                || $now->greaterThanOrEqualTo(Carbon::instance($return->last_overdue_reminder_sent_at)->addHours($settings->returnReminder3Hours))) {
                $this->sendReminder($return, $owner, ReturnReminderPhase::Overdue);

                $return->forceFill(['last_overdue_reminder_sent_at' => $now])->save();

                return true;
            }

            return false;
        }

        if ($return->reminder2_sent_at === null && $now->greaterThanOrEqualTo($reminder2At)) {
            $this->sendReminder($return, $owner, ReturnReminderPhase::Upcoming2);
            $return->forceFill(['reminder2_sent_at' => $now])->save();

            return true;
        }

        if ($return->reminder1_sent_at === null
            && $now->greaterThanOrEqualTo($reminder1At)
            && $reminder1At->greaterThan(Carbon::instance($return->created_at))) {
            $this->sendReminder($return, $owner, ReturnReminderPhase::Upcoming1);
            $return->forceFill(['reminder1_sent_at' => $now])->save();

            return true;
        }

        return false;
    }

    /**
     * @return array{min: CarbonInterface, max: CarbonInterface}
     */
    public function allowedScheduleWindow(?AppSettings $settings = null): array
    {
        $settings ??= $this->settings();
        $now = now();

        return [
            'min' => $now->copy()->addHours($settings->returnReminder2Hours),
            'max' => $now->copy()->addDays($settings->scheduledReturnMaxDays),
        ];
    }

    public function validateScheduledAt(CarbonInterface $scheduledAt, ?AppSettings $settings = null): ?string
    {
        $settings ??= $this->settings();
        $window = $this->allowedScheduleWindow($settings);

        if ($scheduledAt->lessThan($window['min'])) {
            return 'Der Termin muss mindestens '.$settings->returnReminder2Hours.' Stunden in der Zukunft liegen.';
        }

        if ($scheduledAt->greaterThan($window['max'])) {
            return 'Der Termin darf maximal '.$settings->scheduledReturnMaxDays.' Tage in der Zukunft liegen.';
        }

        return null;
    }

    private function sendReminder(AssetReturn $return, User $owner, ReturnReminderPhase $phase): void
    {
        $owner->notify(new ReturnReminderNotification($return, $phase));
    }

    private function settings(): AppSettings
    {
        $settings = IntranetAppAssetsSettings::current()?->settings;

        return $settings instanceof AppSettings ? $settings : new AppSettings;
    }
}
