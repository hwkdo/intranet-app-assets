<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\SeventhingsMappingConfig;
use Hwkdo\IntranetAppAssets\Support\AssetItexiaRoomFieldsSync;
use Hwkdo\SeventhingsLaravel\Client;
use Hwkdo\SeventhingsLaravel\SeventhingsLaravel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ItexiaRoomsSyncCommand extends Command
{
    protected $signature = 'intranet-app-assets:itexia-rooms-sync
                            {--details : Pro Asset ausgeben}
                            {--limit=200 : Maximal so viele Datensätze aktualisieren}
                            {--asset-id= : Nur dieses Asset (ID), sofern itexia_uuid gesetzt}
                            {--uuids-per-request=50 : Chunk-Größe für findAssetsByUuids}';

    protected $description = 'Aktualisiert itexia_actual_room_id und itexia_target_room_id per Batch-Abfrage (findAssetsByUuids).';

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
        Log::info('intranet-app-assets:itexia-rooms-sync gestartet');

        $seventhingsClass = SeventhingsLaravel::class;
        if (! class_exists($seventhingsClass) || ! $this->laravel->bound($seventhingsClass)) {
            $this->warn('Seventhings-Laravel ist nicht verfügbar.');
            Log::warning('intranet-app-assets:itexia-rooms-sync abgebrochen: Seventhings-Laravel nicht verfügbar');

            return self::FAILURE;
        }

        /** @var Client $client */
        $client = $this->laravel->make($seventhingsClass);
        $verbose = $this->option('details');
        $limit = max(1, (int) $this->option('limit'));
        $assetId = $this->option('asset-id') !== null ? (int) $this->option('asset-id') : null;
        $uuidsPerRequest = max(1, min(100, (int) $this->option('uuids-per-request')));

        $query = Asset::query()
            ->whereNotNull('itexia_uuid')
            ->where('itexia_uuid', '!=', '')
            ->orderByRaw('itexia_rooms_synced_at IS NULL DESC')
            ->orderBy('itexia_rooms_synced_at', 'asc')
            ->limit($limit);

        if ($assetId !== null && $assetId > 0) {
            $query->whereKey($assetId);
        }

        $assets = $query->get();
        $total = $assets->count();
        $this->info("Aktualisiere Itexia-Räume für {$total} Asset(s)…");
        Log::info('intranet-app-assets:itexia-rooms-sync', [
            'assets_to_process' => $total,
            'limit' => $limit,
            'asset_id' => $assetId,
            'uuids_per_request' => $uuidsPerRequest,
        ]);

        if ($total === 0) {
            return self::SUCCESS;
        }

        $updated = 0;
        $skippedError = 0;
        $uuidChunks = array_chunk(
            $assets->pluck('itexia_uuid')->map(fn ($u) => trim((string) $u))->filter()->unique()->values()->all(),
            $uuidsPerRequest
        );

        foreach ($uuidChunks as $chunk) {
            if ($chunk === []) {
                continue;
            }

            try {
                $itexiaAssets = $client->findAssetsByUuids($chunk, null, $uuidsPerRequest, 1000);
            } catch (Throwable $e) {
                if ($this->isRateLimitException($e)) {
                    $this->error('Rate Limit (429/420 Too Many Requests) erreicht. Abbruch.');
                    Log::warning('intranet-app-assets:itexia-rooms-sync abgebrochen: Rate Limit', [
                        'message' => $e->getMessage(),
                    ]);

                    return self::FAILURE;
                }
                $skippedError += count($chunk);
                if ($verbose) {
                    $this->line('  Chunk-Fehler: '.$e->getMessage());
                }
                Log::warning('intranet-app-assets:itexia-rooms-sync chunk failed', [
                    'message' => $e->getMessage(),
                    'exception' => get_debug_type($e),
                ]);

                continue;
            }

            $byUuid = $assets->keyBy(fn (Asset $a): string => strtolower(trim((string) $a->itexia_uuid)));

            foreach ($itexiaAssets as $itexiaAsset) {
                $uuid = SeventhingsMappingConfig::getSeventhingsObjectId($itexiaAsset);
                if ($uuid === null || $uuid === '') {
                    continue;
                }
                $key = strtolower(trim((string) $uuid));
                $asset = $byUuid->get($key);
                if ($asset === null) {
                    continue;
                }

                AssetItexiaRoomFieldsSync::applyFromItexiaAsset($asset, $itexiaAsset, true);
                $updated++;
                if ($verbose) {
                    $this->line("  Asset #{$asset->id}: Ist-/Soll-Raum gespeichert.");
                }
            }
        }

        Log::info('intranet-app-assets:itexia-rooms-sync beendet', [
            'updated' => $updated,
            'skipped_error_uuids' => $skippedError,
        ]);

        $this->info("  → {$updated} Asset(s) mit Itexia-Raumfeldern aktualisiert.");
        if ($skippedError > 0) {
            $this->line("  Hinweis: {$skippedError} UUID(s) in fehlgeschlagenen Chunks nicht verarbeitet.");
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
