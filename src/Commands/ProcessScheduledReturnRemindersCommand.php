<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Services\ScheduledReturnReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ProcessScheduledReturnRemindersCommand extends Command
{
    protected $signature = 'intranet-app-assets:return-reminders
                            {--at= : Simulierte aktuelle Zeit für Tests (z. B. "2026-09-10 14:00" oder "2026-09-10 14:00:00")}';

    protected $description = 'Versendet Erinnerungen für geplante Asset-Rückgaben (Scheduler).';

    public function handle(ScheduledReturnReminderService $service): int
    {
        $now = $this->resolveNow();

        if ($this->option('at') !== null) {
            $this->warn('Testmodus: simulierte Zeit '.$now->timezone(config('app.timezone'))->format('d.m.Y H:i:s T'));
        }

        $sent = $service->processDueReminders($now);

        $this->info("Versendete Erinnerungen: {$sent}");

        return self::SUCCESS;
    }

    private function resolveNow(): Carbon
    {
        $at = $this->option('at');

        if ($at === null || $at === '') {
            return now();
        }

        try {
            return Carbon::parse($at, config('app.timezone'));
        } catch (InvalidArgumentException) {
            $this->fail('Ungültiges Datum für --at. Beispiel: --at="2026-09-10 14:00"');
        }
    }
}
