<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Services\HandoverSupersessionService;
use Illuminate\Console\Command;

class CleanupStaleHandoversCommand extends Command
{
    protected $signature = 'intranet-app-assets:cleanup-stale-handovers
                            {--dry-run : Nur anzeigen, nichts superseden}
                            {--actor= : Optional user_id für superseded_by_user_id}';

    protected $description = 'Supersedet bestätigte Übergaben, die nicht mehr zum aktuellen Asset-Lifecycle passen.';

    public function handle(HandoverSupersessionService $supersession): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $actorOption = $this->option('actor');
        $actorId = is_numeric($actorOption) ? (int) $actorOption : null;

        $stale = $supersession->staleConfirmedHandovers();

        if ($stale->isEmpty()) {
            $this->info('Keine veralteten bestätigten Übergaben gefunden.');

            return self::SUCCESS;
        }

        $rows = $stale->map(function ($handover): array {
            $asset = $handover->asset;

            return [
                $handover->id,
                $handover->asset_id,
                $handover->recipient_user_id ?? '—',
                $asset?->user_id ?? '—',
                $asset?->is_in_stock ? 'ja' : 'nein',
                $handover->confirmed_at?->toDateTimeString() ?? '—',
                $handover->assetReturns->isNotEmpty() ? 'ja' : 'nein',
            ];
        })->all();

        $this->table(
            ['Handover', 'Asset', 'Empfänger', 'Owner', 'Lager', 'Confirmed', 'Hat Return'],
            $rows,
        );

        if ($dryRun) {
            $this->info($stale->count().' Handover(s) würden supersedet. Ohne --dry-run ausführen zum Anwenden.');

            return self::SUCCESS;
        }

        foreach ($stale as $handover) {
            $supersession->supersede($handover, $actorId, 'cleanup_stale_confirmed_handover');
            $this->line("  Handover #{$handover->id} (Asset #{$handover->asset_id}) supersedet.");
        }

        $this->info($stale->count().' Handover(s) supersedet.');

        return self::SUCCESS;
    }
}
