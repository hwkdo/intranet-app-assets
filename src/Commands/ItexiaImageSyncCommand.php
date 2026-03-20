<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Enums\ItexiaPictureSyncOutcome;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Services\ItexiaAssetMediaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ItexiaImageSyncCommand extends Command
{
    protected $signature = 'intranet-app-assets:itexia-image-sync
                            {--details : Pro Asset ausgeben, was synchronisiert wurde}
                            {--limit=200 : Maximal verfügbares API-Budget (Requests) pro Lauf}
                            {--asset-id= : Nur dieses Asset (ID) synchronisieren}';

    protected $description = 'Synchronisiert Bilder zwischen lokalen Assets und Itexia (pull/push), ohne lokale Bilder zu überschreiben.';

    public function handle(): int
    {
        $lock = Cache::lock('intranet-app-assets:itexia-api-lock', 3600);
        if (! $lock->get()) {
            $this->warn('Itexia-API wird bereits von einem anderen Sync-Command genutzt. Dieser Lauf wird übersprungen.');

            return self::SUCCESS;
        }

        try {
            return $this->runSync();
        } finally {
            $lock->release();
        }
    }

    private function runSync(): int
    {
        Log::info('intranet-app-assets:itexia-image-sync gestartet');

        $seventhingsClass = 'Hwkdo\SeventhingsLaravel\SeventhingsLaravel';
        if (! class_exists($seventhingsClass) || ! $this->laravel->bound($seventhingsClass)) {
            $this->warn('Seventhings-Laravel ist nicht verfügbar. Bitte das Paket hwkdo/seventhings-laravel installieren und konfigurieren.');
            Log::warning('intranet-app-assets:itexia-image-sync abgebrochen: Seventhings-Laravel nicht verfügbar');

            return self::FAILURE;
        }

        $client = $this->laravel->make($seventhingsClass);
        $service = new ItexiaAssetMediaSyncService;

        $verbose = (bool) $this->option('details');
        $apiLimit = max(1, (int) $this->option('limit'));
        $assetId = $this->option('asset-id') !== null ? (int) $this->option('asset-id') : null;

        // Konservativ: max. 3 API-Requests pro Asset (findAsset + data + thumbnail oder upload + add-file).
        $assetBudget = max(1, intdiv($apiLimit, 3));

        $query = Asset::query()
            ->whereNotNull('itexia_id')
            ->where('itexia_id', '!=', '')
            ->whereNotNull('itexia_uuid')
            ->where('itexia_uuid', '!=', '')
            ->orderBy('itexia_check_at', 'asc')
            ->limit($assetBudget);

        if ($assetId !== null && $assetId > 0) {
            $query->whereKey($assetId);
        }

        $assets = $query->get();
        $this->info(
            'Prüfe '.$assets->count().' Assets für Bild-Sync'
            .($assetId !== null && $assetId > 0 ? ' (Asset-ID: '.$assetId.')' : '')
            .' (API-Budget: '.$apiLimit.', konservatives Asset-Limit: '.$assetBudget.')…'
        );

        $processed = 0;
        $failed = 0;
        $skippedNotFound = 0;
        $pulled = 0;
        $pushed = 0;
        $skippedBothHave = 0;
        $skippedNoRemote = 0;
        $skippedBothMissing = 0;

        foreach ($assets as $asset) {
            $processed++;
            $barcode = trim((string) $asset->itexia_id);
            if ($barcode === '') {
                continue;
            }

            try {
                $itexiaAsset = $client->findAsset($barcode);
                if ($itexiaAsset === null) {
                    $skippedNotFound++;
                    if ($verbose) {
                        $this->line("  Asset #{$asset->id}: in Itexia nicht gefunden.");
                    }
                    $asset->itexia_check_at = now();
                    $asset->save();

                    continue;
                }

                $outcome = $service->syncBidirectional($asset, $client, $itexiaAsset);
                match ($outcome) {
                    ItexiaPictureSyncOutcome::PulledFromItexia => $pulled++,
                    ItexiaPictureSyncOutcome::PushedToItexia => $pushed++,
                    ItexiaPictureSyncOutcome::SkippedBothHaveImage => $skippedBothHave++,
                    ItexiaPictureSyncOutcome::SkippedNoPictureInApi => $skippedNoRemote++,
                    ItexiaPictureSyncOutcome::SkippedBothMissingImage => $skippedBothMissing++,
                    ItexiaPictureSyncOutcome::Failed => $failed++,
                };

                if ($verbose) {
                    $this->line('  Asset #'.$asset->id.': '.$outcome->name);
                }

                $asset->itexia_check_at = now();
                $asset->save();
            } catch (Throwable $e) {
                if ($this->isRateLimitException($e)) {
                    $this->error('Rate Limit (429/420 Too Many Requests) erreicht. Abbruch.');
                    Log::warning('intranet-app-assets:itexia-image-sync abgebrochen: Rate Limit', [
                        'asset_id' => $asset->id,
                        'barcode' => $barcode,
                        'processed' => $processed,
                    ]);

                    return self::FAILURE;
                }

                $failed++;
                Log::warning('ItexiaImageSync fehlgeschlagen', [
                    'asset_id' => $asset->id,
                    'barcode' => $barcode,
                    'message' => $e->getMessage(),
                ]);
                if ($verbose) {
                    $this->line("  Asset #{$asset->id}: Fehler – ".$e->getMessage());
                }
            }
        }

        Log::info('intranet-app-assets:itexia-image-sync beendet', [
            'processed' => $processed,
            'pulled' => $pulled,
            'pushed' => $pushed,
            'skipped_not_found' => $skippedNotFound,
            'skipped_both_have_image' => $skippedBothHave,
            'skipped_no_picture_in_api' => $skippedNoRemote,
            'skipped_both_missing_image' => $skippedBothMissing,
            'failed' => $failed,
            'api_limit' => $apiLimit,
        ]);

        $this->info("  → {$pulled} Bild(er) aus Itexia übernommen.");
        $this->info("  → {$pushed} lokales/lokale Bild(er) nach Itexia übertragen.");
        if ($failed > 0 || $skippedNotFound > 0 || $skippedBothHave > 0 || $skippedNoRemote > 0 || $skippedBothMissing > 0) {
            $this->line(
                '  Übersprungen/Fehler: '
                .$skippedNotFound.' nicht gefunden, '
                .$skippedBothHave.' bereits beidseitig vorhanden, '
                .$skippedNoRemote.' API ohne Bild (lokal vorhanden), '
                .$skippedBothMissing.' beidseitig ohne Bild, '
                .$failed.' fehlgeschlagen.'
            );
        }

        return self::SUCCESS;
    }

    private function isRateLimitException(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'too many requests')
            || str_contains($message, 'rate limit')
            || str_contains($message, '429')
            || str_contains($message, '420')) {
            return true;
        }

        if (method_exists($e, 'getResponse') && $e->getResponse() !== null) {
            $statusCode = $e->getResponse()->getStatusCode();

            return $statusCode === 429 || $statusCode === 420;
        }

        return false;
    }
}
