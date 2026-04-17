<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Services\AssetItexiaRoomSearchHintResolver;
use Hwkdo\IntranetAppAssets\SeventhingsMappingConfig;
use Hwkdo\IntranetAppAssets\Support\ItexiaRoomHintMatcher;
use Hwkdo\IntranetAppAssets\Support\SeventhingsMinuteApiBudget;
use Hwkdo\SeventhingsLaravel\Client;
use Hwkdo\SeventhingsLaravel\Events\ItexiaAssetActualRoomUpdated;
use Hwkdo\SeventhingsLaravel\Models\Asset as ItexiaAsset;
use Hwkdo\SeventhingsLaravel\Models\Raum as ItexiaRaum;
use Hwkdo\SeventhingsLaravel\SeventhingsLaravel;
use Hwkdo\SeventhingsLaravel\Support\ItexiaRoomInspection;
use Hwkdo\SeventhingsLaravel\Support\SeventhingsObjectUuid;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

class ItexiaPushActualRoomsFromIntranetCommand extends Command
{
    protected $signature = 'intranet-app-assets:itexia-push-actual-room-from-intranet
                            {--dry-run : Kein PATCH an Seventhings, nur Auswertung}
                            {--limit=0 : Max. Anzahl Assets (0 = ohne Limit)}
                            {--asset-id= : Nur dieses lokale Asset (ID)}
                            {--max-requests-per-minute=60 : Obergrenze API-Requests pro Minute}
                            {--uuids-per-chunk=50 : Objekte per GET filter[in] (1 = pro Asset findAsset)}
                            {--no-lock : Kein gemeinsames intranet-app-assets:itexia-api-lock}
                            {--details : Jede Entscheidung loggen}';

    protected $description = 'Setzt in Itexia/Seventhings den Ist-Raum (actual_room) aus Intranet-Daten: zuerst Raum des Besitzers, sonst Standort (location). Nur bei genau einem passenden Seventhings-Raum; API-Last begrenzt (Standard 60/min).';

    public function handle(): int
    {
        $seventhingsClass = SeventhingsLaravel::class;
        if (! class_exists($seventhingsClass) || ! $this->laravel->bound($seventhingsClass)) {
            $this->error('Seventhings-Laravel ist nicht gebunden oder nicht installiert.');

            return self::FAILURE;
        }

        $noLock = (bool) $this->option('no-lock');
        $lock = null;
        if (! $noLock) {
            $lock = Cache::lock('intranet-app-assets:itexia-api-lock', 3600);
            if (! $lock->get()) {
                $this->warn('Itexia-API wird bereits genutzt (Lock intranet-app-assets:itexia-api-lock). Abbruch.');

                return self::SUCCESS;
            }
        }

        try {
            return $this->runPush();
        } finally {
            $lock?->release();
        }
    }

    private function runPush(): int
    {
        Log::info('intranet-app-assets:itexia-push-actual-room-from-intranet gestartet');

        $maxPerMinute = max(1, (int) $this->option('max-requests-per-minute'));
        $budget = new SeventhingsMinuteApiBudget($maxPerMinute);
        $dryRun = (bool) $this->option('dry-run');
        $details = (bool) $this->option('details');
        $limit = max(0, (int) $this->option('limit'));
        $assetId = $this->option('asset-id') !== null && $this->option('asset-id') !== ''
            ? (int) $this->option('asset-id')
            : null;
        $uuidsPerChunk = max(1, min(100, (int) $this->option('uuids-per-chunk')));

        /** @var Client $client */
        $client = $this->laravel->make(SeventhingsLaravel::class);

        $uuidFieldKey = config('intranet-app-assets.seventhings_object_id_key');
        $uuidFieldKey = is_string($uuidFieldKey) && trim($uuidFieldKey) !== '' ? trim($uuidFieldKey) : null;
        $objectUuidFilterField = $this->resolveObjectUuidFilterFieldKey($uuidFieldKey);

        $query = Asset::query()
            ->whereNotNull('itexia_uuid')
            ->where('itexia_uuid', '!=', '')
            ->whereNotNull('itexia_id')
            ->where('itexia_id', '!=', '')
            ->orderBy('id');

        if ($assetId !== null && $assetId > 0) {
            $query->whereKey($assetId);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $assets = $query->get();
        $total = $assets->count();
        $this->info("Verarbeite {$total} Asset(s)…");

        if ($total === 0) {
            return self::SUCCESS;
        }

        try {
            $rooms = $this->loadAllRooms($client, $budget);
        } catch (Throwable $e) {
            $this->error('Raumliste konnte nicht geladen werden: '.$e->getMessage());
            Log::error('intranet-app-assets:itexia-push-actual-room-from-intranet rooms failed', ['exception' => $e]);

            return self::FAILURE;
        }

        $this->line('  Seventhings-Räume geladen: '.$rooms->count());

        $stats = [
            'skipped_no_hint' => 0,
            'skipped_ambiguous' => 0,
            'skipped_no_room_match' => 0,
            'skipped_already_set' => 0,
            'skipped_not_in_itexia_batch' => 0,
            'skipped_find_asset_failed' => 0,
            'patched' => 0,
            'dry_run_would_patch' => 0,
            'errors' => 0,
        ];

        if ($uuidsPerChunk === 1) {
            foreach ($assets as $asset) {
                $this->processOneAssetWithFindAsset(
                    $client,
                    $budget,
                    $rooms,
                    $asset,
                    $dryRun,
                    $details,
                    $stats,
                );
            }
        } else {
            foreach ($assets->chunk($uuidsPerChunk) as $chunk) {
                /** @var Collection<int, Asset> $chunk */
                $uuidList = $chunk->map(fn (Asset $a) => strtolower(trim((string) $a->itexia_uuid)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($uuidList === []) {
                    continue;
                }

                try {
                    $byUuid = $this->fetchItexiaAssetsByUuidChunk(
                        $client,
                        $objectUuidFilterField,
                        $uuidList,
                        1000,
                        $budget,
                    );
                } catch (Throwable $e) {
                    $this->warn('Chunk-Fehler: '.$e->getMessage());
                    Log::warning('intranet-app-assets:itexia-push-actual-room-from-intranet chunk', [
                        'message' => $e->getMessage(),
                    ]);
                    $stats['errors'] += $chunk->count();

                    continue;
                }

                foreach ($chunk as $asset) {
                    $key = strtolower(trim((string) $asset->itexia_uuid));
                    $itexiaAsset = $byUuid->get($key);
                    if ($itexiaAsset === null) {
                        $stats['skipped_not_in_itexia_batch']++;
                        if ($details) {
                            $this->line("  #{$asset->id}: nicht in Chunk-Antwort (UUID).");
                        }

                        continue;
                    }
                    $this->applyRoomUpdateForPair(
                        $client,
                        $budget,
                        $rooms,
                        $asset,
                        $itexiaAsset,
                        $dryRun,
                        $details,
                        $stats,
                    );
                }
            }
        }

        $labelMap = [
            'skipped_no_hint' => 'Übersprungen: kein Raum-Hint',
            'skipped_ambiguous' => 'Übersprungen: Hint mehrdeutig',
            'skipped_no_room_match' => 'Übersprungen: kein passender Raum',
            'skipped_already_set' => 'Übersprungen: Ist-Raum bereits korrekt',
            'skipped_not_in_itexia_batch' => 'Übersprungen: Objekt nicht in Batch-Antwort',
            'skipped_find_asset_failed' => 'Übersprungen: Barcode in Itexia nicht gefunden',
            'patched' => 'actual_room gesetzt (PATCH)',
            'dry_run_would_patch' => '[Dry-run] würde patchen',
            'errors' => 'Fehler',
        ];

        $rows = [];
        foreach ($stats as $key => $count) {
            $rows[] = [$labelMap[$key] ?? $key, (string) $count];
        }

        $this->table(['Kategorie', 'Anzahl'], $rows);

        Log::info('intranet-app-assets:itexia-push-actual-room-from-intranet beendet', $stats);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function processOneAssetWithFindAsset(
        Client $client,
        SeventhingsMinuteApiBudget $budget,
        Collection $rooms,
        Asset $asset,
        bool $dryRun,
        bool $details,
        array &$stats,
    ): void {
        $barcode = trim((string) $asset->itexia_id);
        if ($barcode === '') {
            return;
        }

        try {
            $budget->acquire();
            $itexiaAsset = $client->findAsset($barcode);
        } catch (Throwable $e) {
            $stats['errors']++;
            if ($details) {
                $this->line("  #{$asset->id}: findAsset Fehler: ".$e->getMessage());
            }
            Log::warning('intranet-app-assets:itexia-push-actual-room-from-intranet findAsset', [
                'asset_id' => $asset->id,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if ($itexiaAsset === null) {
            $stats['skipped_find_asset_failed']++;
            if ($details) {
                $this->line("  #{$asset->id}: kein Itexia-Objekt für Barcode.");
            }

            return;
        }

        $this->applyRoomUpdateForPair($client, $budget, $rooms, $asset, $itexiaAsset, $dryRun, $details, $stats);
    }

    /**
     * @param  Collection<int, ItexiaRaum>  $rooms
     * @param  array<string, int>  $stats
     */
    private function applyRoomUpdateForPair(
        Client $client,
        SeventhingsMinuteApiBudget $budget,
        Collection $rooms,
        Asset $asset,
        ItexiaAsset $itexiaAsset,
        bool $dryRun,
        bool $details,
        array &$stats,
    ): void {
        $asset->loadMissing('owner');
        $resolved = AssetItexiaRoomSearchHintResolver::resolve($asset);
        $hint = $resolved['hint'] ?? null;
        if ($hint === null || trim((string) $hint) === '') {
            $stats['skipped_no_hint']++;
            if ($details) {
                $this->line("  #{$asset->id}: kein Raum-Hint (Besitzer-Raum / Standort leer).");
            }

            return;
        }

        $matches = ItexiaRoomHintMatcher::findMatchingRooms($rooms, (string) $hint);
        $count = count($matches);
        if ($count === 0) {
            $stats['skipped_no_room_match']++;
            if ($details) {
                $this->line("  #{$asset->id}: kein Seventhings-Raum zum Hint „{$hint}“.");
            }

            return;
        }
        if ($count > 1) {
            $stats['skipped_ambiguous']++;
            if ($details) {
                $this->line("  #{$asset->id}: mehrdeutiger Hint „{$hint}“ ({$count} Treffer).");
            }

            return;
        }

        $expectedRoomId = $matches[0]['id'];
        $currentRoomId = ItexiaRoomInspection::normalizeRoomIdFromItexiaAsset($itexiaAsset);

        if ($currentRoomId !== null && $currentRoomId === $expectedRoomId) {
            $stats['skipped_already_set']++;
            if ($details) {
                $this->line("  #{$asset->id}: Ist-Raum bereits {$expectedRoomId}.");
            }

            return;
        }

        $objectUuid = SeventhingsObjectUuid::fromItexiaAsset($itexiaAsset);
        if ($objectUuid === null || trim($objectUuid) === '') {
            $stats['errors']++;
            if ($details) {
                $this->line("  #{$asset->id}: keine Objekt-UUID in API-Daten.");
            }

            return;
        }

        $barcode = trim((string) $asset->itexia_id);
        $barcodeForEvent = $barcode !== '' ? $barcode : null;

        if ($dryRun) {
            $stats['dry_run_would_patch']++;
            if ($details) {
                $this->line(sprintf(
                    '  #%d: [dry-run] PATCH actual_room=%d (aktuell %s).',
                    $asset->id,
                    $expectedRoomId,
                    $currentRoomId ?? 'null',
                ));
            }

            return;
        }

        try {
            $budget->acquire();
            $client->updateAsset($objectUuid, ['actual_room' => $expectedRoomId]);
        } catch (Throwable $e) {
            $stats['errors']++;
            if ($details) {
                $this->line("  #{$asset->id}: PATCH fehlgeschlagen: ".$e->getMessage());
            }
            Log::error('intranet-app-assets:itexia-push-actual-room-from-intranet patch', [
                'asset_id' => $asset->id,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        Event::dispatch(new ItexiaAssetActualRoomUpdated($objectUuid, $expectedRoomId, $barcodeForEvent));
        $stats['patched']++;
        if ($details) {
            $this->line("  #{$asset->id}: actual_room={$expectedRoomId} gesetzt.");
        }
    }

    /**
     * @return Collection<int, ItexiaRaum>
     */
    private function loadAllRooms(Client $client, SeventhingsMinuteApiBudget $budget): Collection
    {
        $page = 1;
        $budget->acquire();
        $result = $client->sendRequest('GET', 'rooms?per_page=1000&page='.$page);
        if (! is_object($result) || ! isset($result->items) || ! is_array($result->items)) {
            throw new \RuntimeException(
                is_string($result) ? $result : 'GET rooms: ungültige Antwort.'
            );
        }

        $items = $result->items;
        $total = (int) ($result->total ?? 0);
        $perPageReported = (int) ($result->per_page ?? 1000);
        $currentPage = (int) ($result->page ?? $page);

        while ($total > $perPageReported * $currentPage) {
            $page++;
            $budget->acquire();
            $result = $client->sendRequest('GET', 'rooms?per_page=1000&page='.$page);
            if (! is_object($result) || ! isset($result->items) || ! is_array($result->items)) {
                throw new \RuntimeException(
                    is_string($result) ? $result : 'GET rooms Seite '.$page.': ungültige Antwort.'
                );
            }
            $items = array_merge($items, $result->items);
            $total = (int) ($result->total ?? 0);
            $perPageReported = (int) ($result->per_page ?? 1000);
            $currentPage = (int) ($result->page ?? $page);
        }

        $col = collect();
        foreach ($items as $row) {
            $col->push(new ItexiaRaum($row));
        }

        return $col;
    }

    /**
     * @param  array<int, string>  $uuidChunk
     * @return Collection<string, ItexiaAsset>
     */
    private function fetchItexiaAssetsByUuidChunk(
        Client $client,
        string $fieldKey,
        array $uuidChunk,
        int $perPage,
        SeventhingsMinuteApiBudget $budget,
    ): Collection {
        $rows = [];
        $page = 1;
        $maxPages = 500;
        $paginationComplete = false;

        while ($page <= $maxPages) {
            $endpoint = $this->buildObjectsListEndpoint($fieldKey, $uuidChunk, $perPage, $page);
            $budget->acquire();
            $result = $client->sendRequest('GET', $endpoint);

            if ($this->isRateLimitedResult($result)) {
                sleep(65);
                $budget->acquire();
                $result = $client->sendRequest('GET', $endpoint);
            }

            if (! is_object($result)) {
                throw new \RuntimeException(
                    is_string($result) ? $result : 'GET objects: ungültige Antwort.'
                );
            }

            if (! isset($result->items) || ! is_array($result->items)) {
                throw new \RuntimeException('GET objects: Feld „items“ fehlt.');
            }

            foreach ($result->items as $row) {
                $rows[] = $row;
            }

            $total = (int) ($result->total ?? 0);
            $perPageReported = (int) ($result->per_page ?? $perPage);
            $currentPage = (int) ($result->page ?? $page);

            if ($total <= $perPageReported * $currentPage || count($result->items) === 0) {
                $paginationComplete = true;
                break;
            }

            $page++;
        }

        if (! $paginationComplete) {
            throw new \RuntimeException('GET objects: Pagination-Limit erreicht.');
        }

        $out = collect();
        foreach ($rows as $row) {
            $itexiaAsset = new ItexiaAsset($row);
            $uuid = SeventhingsMappingConfig::getSeventhingsObjectId($itexiaAsset);
            if ($uuid === null || trim((string) $uuid) === '') {
                continue;
            }
            $out->put(strtolower(trim((string) $uuid)), $itexiaAsset);
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $uuids
     */
    private function buildObjectsListEndpoint(string $fieldKey, array $uuids, int $perPage, int $page): string
    {
        $parts = [
            'objects?per_page='.$perPage,
            'page='.$page,
        ];

        foreach ($uuids as $uuid) {
            $parts[] = 'filter['.$fieldKey.'][in][]='.rawurlencode($uuid);
        }

        return implode('&', $parts);
    }

    private function resolveObjectUuidFilterFieldKey(?string $uuidFieldKey): string
    {
        if ($uuidFieldKey !== null && trim($uuidFieldKey) !== '') {
            return trim($uuidFieldKey);
        }

        $configKey = config('seventhings-laravel.object_uuid_key');
        if (is_string($configKey) && trim($configKey) !== '') {
            return trim($configKey);
        }

        return 'asset_uuid';
    }

    private function isRateLimitedResult(mixed $result): bool
    {
        if (is_string($result)) {
            $lower = strtolower($result);

            return str_contains($lower, 'too many')
                || str_contains($lower, '429')
                || str_contains($lower, 'rate limit');
        }

        return false;
    }
}
