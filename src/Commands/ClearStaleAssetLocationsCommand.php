<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Illuminate\Console\Command;

class ClearStaleAssetLocationsCommand extends Command
{
    protected $signature = 'intranet-app-assets:clear-stale-locations
                            {--dry-run : Nur anzeigen, was geleert würde}
                            {--limit=0 : Maximal N Assets verarbeiten (0 = unbegrenzt)}';

    protected $description = 'Entfernt veraltete Standorte bei Assets mit Besitzer (user_id gesetzt, location noch befüllt).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        $query = Asset::query()
            ->whereNotNull('user_id')
            ->whereNotNull('location')
            ->where('location', '!=', '');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $assets = $query->get(['id', 'user_id', 'location']);

        $this->info('Prüfe Assets mit Besitzer und veraltetem Standort…');
        if ($dryRun) {
            $this->warn('DRY-RUN: Es werden keine Änderungen geschrieben.');
        }

        $cleared = 0;

        foreach ($assets as $asset) {
            $cleared++;
            $this->line("  Asset #{$asset->id}: «{$asset->location}» → (leer)");

            if (! $dryRun) {
                $asset->update(['location' => null]);
            }
        }

        $this->line('  → betroffen: '.$cleared);

        return self::SUCCESS;
    }
}
