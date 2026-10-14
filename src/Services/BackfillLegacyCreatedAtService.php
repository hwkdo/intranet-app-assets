<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use App\Services\IntranetLegacyService;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Hwkdo\IntranetAppAssets\Models\Asset;

class BackfillLegacyCreatedAtService
{
    private const int CHUNK_SIZE = 200;

    private const int SAMPLE_LIMIT = 25;

    /**
     * @return array{
     *     legacy_assets: int,
     *     candidates: int,
     *     updated: int,
     *     unchanged: int,
     *     unmatched: int,
     *     invalid: int,
     *     rows: list<array{asset_id: int, legacy_id: int, serial_number: string, from: string, to: string}>
     * }
     */
    public function run(IntranetLegacyService $legacyService, bool $dryRun): array
    {
        $byLegacyId = $this->indexLegacyAssets($legacyService->getAssetsExportAlle('all'));

        $stats = [
            'legacy_assets' => count($byLegacyId),
            'candidates' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'unmatched' => 0,
            'invalid' => 0,
            'rows' => [],
        ];

        Asset::query()
            ->withTrashed()
            ->whereNotNull('legacy_id')
            ->with(['owner', 'type', 'vendor'])
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($assets) use (&$stats, $byLegacyId, $dryRun): void {
                foreach ($assets as $asset) {
                    $this->applyLegacyCreatedAt($asset, $byLegacyId, $dryRun, $stats);
                }
            });

        return $stats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $legacyAssets
     * @return array<int, array<string, mixed>>
     */
    private function indexLegacyAssets(array $legacyAssets): array
    {
        $byLegacyId = [];

        foreach ($legacyAssets as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            $byLegacyId[$legacyId] = $legacy;
        }

        return $byLegacyId;
    }

    /**
     * @param  array<int, array<string, mixed>>  $byLegacyId
     * @param  array{
     *     legacy_assets: int,
     *     candidates: int,
     *     updated: int,
     *     unchanged: int,
     *     unmatched: int,
     *     invalid: int,
     *     rows: list<array{asset_id: int, legacy_id: int, serial_number: string, from: string, to: string}>
     * }  $stats
     */
    private function applyLegacyCreatedAt(Asset $asset, array $byLegacyId, bool $dryRun, array &$stats): void
    {
        $stats['candidates']++;

        $legacyId = (int) $asset->legacy_id;
        $legacy = $byLegacyId[$legacyId] ?? null;
        if ($legacy === null) {
            $stats['unmatched']++;

            return;
        }

        $createdAt = $this->parseNullableDatetime($legacy['created_at'] ?? null);
        if ($createdAt === null) {
            $stats['invalid']++;

            return;
        }

        $current = $asset->created_at?->format('Y-m-d H:i:s');
        if ($current === $createdAt) {
            $stats['unchanged']++;

            return;
        }

        $stats['updated']++;
        if (count($stats['rows']) < self::SAMPLE_LIMIT) {
            $stats['rows'][] = [
                'asset_id' => (int) $asset->id,
                'legacy_id' => $legacyId,
                'serial_number' => (string) $asset->serial_number,
                'from' => $current ?? '',
                'to' => $createdAt,
            ];
        }

        if ($dryRun) {
            return;
        }

        Asset::withoutTimestamps(function () use ($asset, $createdAt): void {
            $asset->created_at = $createdAt;
            $asset->save();
        });
    }

    private function parseNullableDatetime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $dt = $value instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($value)
                : new DateTimeImmutable(trim((string) $value));
        } catch (Exception) {
            return null;
        }

        $year = (int) $dt->format('Y');
        if ($year < 1000 || $year > 9999) {
            return null;
        }

        return $dt
            ->setTimezone(new DateTimeZone((string) config('app.timezone')))
            ->format('Y-m-d H:i:s');
    }
}
