<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Commands;

use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppAssets\Services\BackfillLegacyCreatedAtService;
use Hwkdo\IntranetAppAssets\Support\AssetItexiaInventoryNotifyContext;
use Illuminate\Console\Command;

class BackfillLegacyCreatedAtCommand extends Command
{
    protected $signature = 'intranet-app-assets:backfill-legacy-created-at
                            {--dry-run : Nur anzeigen, welche created_at-Werte überschrieben würden}';

    protected $description = 'Überschreibt created_at importierter Assets mit dem originalen Zeitpunkt aus dem Legacy-Export (scope all).';

    public function handle(IntranetLegacyService $legacyService, BackfillLegacyCreatedAtService $backfill): int
    {
        if (! config('legacy.base_api_url') || ! config('legacy.base_api_token')) {
            $this->error('Legacy-API nicht konfiguriert: INTRANET_LEGACY_BASE_API_URL und INTRANET_LEGACY_API_TOKEN müssen gesetzt sein.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY-RUN: Es werden keine created_at-Werte geschrieben.');
        }

        $result = AssetItexiaInventoryNotifyContext::suppress(
            fn (): array => $backfill->run($legacyService, $dryRun),
        );

        if ($result['legacy_assets'] === 0) {
            $this->error('Der Legacy-Export hat keine Assets geliefert. created_at wurde nicht verändert.');

            return self::FAILURE;
        }

        $this->line('Legacy-Assets im Export: '.$result['legacy_assets']);
        $this->line('Lokale Assets mit legacy_id: '.$result['candidates']);
        $this->line(($dryRun ? 'Würde überschreiben' : 'Überschrieben').': '.$result['updated']);
        $this->line('Bereits korrekt: '.$result['unchanged']);
        $this->line('Ohne Treffer im Export: '.$result['unmatched']);
        $this->line('Ohne gültiges created_at: '.$result['invalid']);

        if ($result['rows'] !== []) {
            $this->newLine();
            $this->table(
                ['Asset-ID', 'Legacy-ID', 'Seriennummer', 'Bisher', 'Legacy created_at'],
                array_map(static fn (array $row): array => [
                    $row['asset_id'],
                    $row['legacy_id'],
                    $row['serial_number'],
                    $row['from'],
                    $row['to'],
                ], $result['rows']),
            );

            $hidden = $result['updated'] - count($result['rows']);
            if ($hidden > 0) {
                $this->line('Weitere '.$hidden.' Änderungen sind nicht in der Tabelle.');
            }
        }

        return self::SUCCESS;
    }
}
