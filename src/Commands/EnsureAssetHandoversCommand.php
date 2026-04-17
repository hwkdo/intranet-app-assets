<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Services\AssetOwnerHandoverAutomationService;
use Illuminate\Console\Command;

class EnsureAssetHandoversCommand extends Command
{
    protected $signature = 'intranet-app-assets:ensure-handovers
                            {--dry-run : Nur anzeigen, keine Handovers anlegen}';

    protected $description = 'Legt für alle Assets mit Besitzer (user_id) einen Handover an, falls noch keiner existiert.';

    public function handle(AssetOwnerHandoverAutomationService $automation): int
    {
        $dryRun = $this->option('dry-run');

        $assets = Asset::query()
            ->whereNotNull('user_id')
            ->get();

        $created = 0;

        foreach ($assets as $asset) {
            $hasHandover = $asset->handovers()
                ->where('recipient_user_id', $asset->user_id)
                ->exists();

            if (! $hasHandover) {
                if (! $dryRun) {
                    $automation->ensureAnyHandoverForCurrentOwner($asset);
                }
                $created++;
                $this->line("  Asset #{$asset->id} ({$asset->display_name}): ".($dryRun ? 'würde Handover anlegen' : 'Handover angelegt'));
            }
        }

        if ($created === 0) {
            $this->info('Alle Assets mit Besitzer haben bereits einen passenden Handover.');
        } else {
            $this->info($dryRun
                ? "{$created} Handover(s) würden angelegt. Ohne --dry-run ausführen zum Anlegen."
                : "{$created} Handover(s) angelegt.");
        }

        return self::SUCCESS;
    }
}
