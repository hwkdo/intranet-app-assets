<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\SeventhingsMappingConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ItexiaUuidSyncCommand extends Command
{
    protected $signature = 'intranet-app-assets:itexia-uuid-sync
                            {--details : Pro Asset ausgeben, ob in Seventhings gefunden und UUID gesetzt}
                            {--limit= : Maximal so viele Datensätze prüfen (ohne Angabe: alle)}
                            {--asset-id= : Nur dieses Asset (ID) synchronisieren}';

    protected $description = 'Findet für Assets mit Itexia-ID die zugehörige Seventhings-UUID und speichert sie in itexia_uuid.';

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
        Log::info('intranet-app-assets:itexia-uuid-sync gestartet');

        $seventhingsClass = 'Hwkdo\SeventhingsLaravel\SeventhingsLaravel';
        if (! class_exists($seventhingsClass) || ! $this->laravel->bound($seventhingsClass)) {
            $this->warn('Seventhings-Laravel ist nicht verfügbar. Bitte das Paket hwkdo/seventhings-laravel installieren und konfigurieren.');
            Log::warning('intranet-app-assets:itexia-uuid-sync abgebrochen: Seventhings-Laravel nicht verfügbar');

            return self::FAILURE;
        }

        $client = $this->laravel->make($seventhingsClass);
        $verbose = $this->option('details');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $assetId = $this->option('asset-id') !== null ? (int) $this->option('asset-id') : null;

        $query = Asset::query()
            ->whereNotNull('itexia_id')
            ->where('itexia_id', '!=', '')
            ->whereNull('itexia_uuid')
            ->orderBy('itexia_check_at', 'asc');

        if ($assetId !== null && $assetId > 0) {
            $query->whereKey($assetId);
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $assets = $query->get();
        $total = $assets->count();
        $this->info(
            'Prüfe '.$total.' Assets mit fehlender itexia_uuid'
            .($assetId !== null && $assetId > 0 ? ' (Asset-ID: '.$assetId.')' : '')
            .($limit !== null ? ' (Limit: '.$limit.')' : '')
            .'…'
        );
        Log::info('intranet-app-assets:itexia-uuid-sync', [
            'assets_to_process' => $total,
            'limit' => $limit,
            'asset_id' => $assetId,
        ]);

        $updated = 0;
        $skippedEmpty = 0;
        $skippedError = 0;
        $skippedNotFound = 0;
        $skippedNoUuid = 0;
        $processed = 0;

        foreach ($assets as $asset) {
            $processed++;
            $barcode = trim((string) $asset->itexia_id);
            if ($barcode === '') {
                $skippedEmpty++;
                if ($verbose) {
                    $this->line("  Asset #{$asset->id}: Itexia-ID leer – übersprungen.");
                }
                Log::debug('ItexiaUuidSync Asset übersprungen (leer)', ['asset_id' => $asset->id]);

                continue;
            }

            try {
                $itexiaAsset = $client->findAsset($barcode);
            } catch (Throwable $e) {
                if ($this->isRateLimitException($e)) {
                    $this->error('Rate Limit (429/420 Too Many Requests) erreicht. Abbruch.');
                    Log::warning('intranet-app-assets:itexia-uuid-sync abgebrochen: Rate Limit', [
                        'asset_id' => $asset->id,
                        'barcode' => $barcode,
                        'processed' => $processed,
                        'updated' => $updated,
                    ]);

                    return self::FAILURE;
                }
                $skippedError++;
                if ($verbose) {
                    $this->line("  Asset #{$asset->id} (Barcode: {$barcode}): Fehler – ".$e->getMessage());
                }
                Log::warning('ItexiaUuidSync Seventhings-Abfrage fehlgeschlagen', [
                    'asset_id' => $asset->id,
                    'barcode' => $barcode,
                    'message' => $e->getMessage(),
                    'exception' => get_debug_type($e),
                ]);
                $asset->itexia_check_at = now();
                $asset->save();

                continue;
            }

            if ($itexiaAsset === null) {
                $skippedNotFound++;
                if ($verbose) {
                    $this->line("  Asset #{$asset->id} (Barcode: {$barcode}): in Seventhings nicht gefunden.");
                }
                Log::debug('ItexiaUuidSync Asset in Seventhings nicht gefunden', ['asset_id' => $asset->id, 'barcode' => $barcode]);
                $asset->itexia_check_at = now();
                $asset->save();

                continue;
            }

            $uuid = SeventhingsMappingConfig::getSeventhingsObjectId($itexiaAsset);

            if ($asset->itexia_uuid === null || $asset->itexia_uuid === '') {
                if ($uuid === null || $uuid === '') {
                    $skippedNoUuid++;
                    if ($verbose) {
                        $this->line("  Asset #{$asset->id} (Barcode: {$barcode}): Datensatz gefunden, aber keine UUID ermittelbar.");
                    }
                    Log::warning('ItexiaUuidSync Datensatz ohne UUID', [
                        'asset_id' => $asset->id,
                        'barcode' => $barcode,
                    ]);
                } else {
                    $asset->itexia_uuid = (string) $uuid;
                    $asset->save();
                    $asset->historyEntries()->create([
                        'event' => AssetHistory::EventUpdated,
                        'user_id' => null,
                    ]);
                    $updated++;
                    if ($verbose) {
                        $this->line("  Asset #{$asset->id} (Barcode: {$barcode}): UUID gespeichert.");
                    }
                    Log::info('ItexiaUuidSync Asset itexia_uuid gesetzt', [
                        'asset_id' => $asset->id,
                        'barcode' => $barcode,
                        'itexia_uuid' => $uuid,
                    ]);
                }
            }

            $asset->itexia_check_at = now();
            $asset->save();
        }

        $summary = [
            'total_processed' => $processed,
            'updated' => $updated,
            'skipped_empty_barcode' => $skippedEmpty,
            'skipped_error' => $skippedError,
            'skipped_not_found' => $skippedNotFound,
            'skipped_no_uuid' => $skippedNoUuid,
        ];
        Log::info('intranet-app-assets:itexia-uuid-sync beendet', $summary);

        $this->info("  → {$updated} Asset(s) mit itexia_uuid aktualisiert.");
        if ($skippedError > 0 || $skippedNotFound > 0 || $skippedNoUuid > 0) {
            $this->line('  Übersprungen: '.$skippedNotFound.' nicht gefunden, '.$skippedError.' Fehler, '.$skippedNoUuid.' ohne UUID, '.$skippedEmpty.' leer.');
        }

        return self::SUCCESS;
    }

    private function isRateLimitException(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        if (str_contains($message, 'too many requests')
            || str_contains($message, 'rate limit')
            || str_contains($message, '429')
            || str_contains($e->getMessage(), '420')) {
            return true;
        }
        if (method_exists($e, 'getResponse') && $e->getResponse() !== null) {
            $statusCode = $e->getResponse()->getStatusCode();

            return $statusCode === 429 || $statusCode === 420;
        }

        return false;
    }
}
