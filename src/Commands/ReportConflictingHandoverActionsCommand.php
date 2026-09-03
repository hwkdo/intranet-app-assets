<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Support\ConflictingHandoverActionReporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ReportConflictingHandoverActionsCommand extends Command
{
    protected $signature = 'intranet-app-assets:report-conflicting-handover-actions
                            {--csv= : Optionaler Pfad für CSV-Export}';

    protected $description = 'Listet Assets, bei denen die Alle-Assets-Liste zugleich Übergeben und Rückgabe anbieten würde.';

    public function handle(ConflictingHandoverActionReporter $reporter): int
    {
        $rows = $reporter->rows();

        if ($rows->isEmpty()) {
            $this->info('Keine Assets mit gleichzeitigem Übergeben- und Rückgabe-Button gefunden.');

            return self::SUCCESS;
        }

        $tableRows = $rows->map(fn (array $row): array => [
            $row['asset_id'],
            $row['serial_number'] ?? '—',
            $row['user_id'] ?? '—',
            $row['is_in_stock'] ? 'ja' : 'nein',
            $row['open_handover_id'] ?? '—',
            $row['open_recipient_user_id'] ?? '—',
            $row['returnable_handover_id'] ?? '—',
            $row['returnable_recipient_user_id'] ?? '—',
            $row['returnable_has_completed_return'] ? 'ja' : 'nein',
            $row['returnable_is_superseded'] ? 'ja' : 'nein',
            $row['pattern'],
        ])->all();

        $this->table(
            [
                'Asset',
                'SN',
                'Owner',
                'Lager',
                'Open HO',
                'Open Empf.',
                'Return HO',
                'Return Empf.',
                'Return fertig?',
                'Superseded?',
                'Muster',
            ],
            $tableRows,
        );

        $byPattern = $rows->groupBy('pattern')->map->count();
        $this->newLine();
        $this->info('Summe: '.$rows->count().' Asset(s)');
        foreach ($byPattern as $pattern => $count) {
            $this->line("  - {$pattern}: {$count}");
        }

        $csvPath = $this->option('csv');
        if (is_string($csvPath) && $csvPath !== '') {
            $this->writeCsv($csvPath, $rows->all());
            $this->info("CSV geschrieben: {$csvPath}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $directory = dirname($path);
        if ($directory !== '' && $directory !== '.' && ! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException("CSV-Datei konnte nicht geschrieben werden: {$path}");
        }

        try {
            fputcsv($handle, array_keys($rows[0]), ';');
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['asset_id'],
                    $row['serial_number'],
                    $row['model'],
                    $row['display_name'],
                    $row['user_id'],
                    $row['is_in_stock'] ? 1 : 0,
                    $row['location'],
                    $row['open_handover_id'],
                    $row['open_recipient_user_id'],
                    $row['open_created_at'],
                    $row['returnable_handover_id'],
                    $row['returnable_recipient_user_id'],
                    $row['returnable_confirmed_at'],
                    $row['returnable_has_completed_return'] ? 1 : 0,
                    $row['returnable_is_superseded'] ? 1 : 0,
                    $row['pattern'],
                ], ';');
            }
        } finally {
            fclose($handle);
        }
    }
}
