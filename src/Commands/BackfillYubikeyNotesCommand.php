<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Commands;

use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppAssets\Services\BackfillYubikeyNotesService;
use Illuminate\Console\Command;
use RuntimeException;

class BackfillYubikeyNotesCommand extends Command
{
    protected $signature = 'intranet-app-assets:backfill-yubikey-notes
                            {--dry-run : Nur anzeigen, welche Notizen angelegt würden}
                            {--with-trashed : Auch soft-deleted Yubikeys berücksichtigen}';

    protected $description = 'Zieht fehlende Legacy-Kommentare für Yubikeys ohne PIN-Notiz nach.';

    public function handle(IntranetLegacyService $legacyService, BackfillYubikeyNotesService $backfill): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $withTrashed = (bool) $this->option('with-trashed');

        if ($dryRun) {
            $this->warn('DRY-RUN: Es werden keine Notizen geschrieben.');
        }

        try {
            $result = $backfill->run($legacyService, $dryRun, $withTrashed);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Kandidaten: '.$result['candidates']);
        $this->line(($dryRun ? 'Neue Notizen (nicht geschrieben)' : 'Neue Notizen').': '.$result['created']);
        $this->line('Davon Assets mit PIN-Text: '.$result['assets_with_pin_text']);
        $this->line('Bereits vorhandene Legacy-Notizen: '.$result['already_present']);
        $this->line('Assets ohne Legacy-Kommentar: '.$result['assets_without_legacy_notes']);

        if ($result['rows'] !== []) {
            $this->newLine();
            $this->table(
                ['Asset-ID', 'Legacy-ID', 'Seriennummer', 'Neue Notizen', 'PIN-Text'],
                array_map(static fn (array $row): array => [
                    $row['asset_id'],
                    $row['legacy_id'],
                    $row['serial_number'],
                    $row['notes'],
                    $row['has_pin_text'] ? 'ja' : 'nein',
                ], $result['rows']),
            );
        }

        return self::SUCCESS;
    }
}
