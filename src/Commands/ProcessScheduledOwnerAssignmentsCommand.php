<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Services\ScheduledOwnerAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ProcessScheduledOwnerAssignmentsCommand extends Command
{
    protected $signature = 'intranet-app-assets:process-scheduled-owner-assignments
                            {--at= : Simulierte aktuelle Zeit für Tests (z. B. "2026-09-10 14:00")}';

    protected $description = 'Führt fällige geplante Asset-Besitzerwechsel aus (z. B. Austritt „Werde ich erhalten“).';

    public function handle(ScheduledOwnerAssignmentService $service): int
    {
        $now = $this->resolveNow();

        if ($this->option('at') !== null) {
            $this->warn('Testmodus: simulierte Zeit '.$now->timezone(config('app.timezone'))->format('d.m.Y H:i:s T'));
        }

        $processed = $service->processDue($now);

        $this->info("Verarbeitete Besitzerwechsel: {$processed}");

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
