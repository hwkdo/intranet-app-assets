<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Illuminate\Support\Collection;

/**
 * Rückgabe nur für den aktuellen Lifecycle: bestätigte aktive Übergabe an den
 * aktuellen Besitzer, ohne jegliche Rückgabe und ohne parallele offene Übergabe.
 */
final class ReturnInitiatableHandoverResolver
{
    /**
     * @param  iterable<int|string>  $assetIds
     * @return Collection<int, Handover>
     */
    public function forAssetIds(iterable $assetIds): Collection
    {
        $ids = collect($assetIds)
            ->map(static fn (int|string $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $openAssetIds = Handover::query()
            ->open()
            ->whereIn('asset_id', $ids)
            ->pluck('asset_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->all();

        $eligibleAssetIds = $ids
            ->reject(static fn (int $id): bool => in_array($id, $openAssetIds, true))
            ->values();

        if ($eligibleAssetIds->isEmpty()) {
            return collect();
        }

        $assetsTable = (new Asset)->getTable();
        $handoversTable = (new Handover)->getTable();

        return Handover::query()
            ->select("{$handoversTable}.*")
            ->join($assetsTable, "{$assetsTable}.id", '=', "{$handoversTable}.asset_id")
            ->whereIn("{$handoversTable}.asset_id", $eligibleAssetIds->all())
            ->whereNull("{$handoversTable}.superseded_at")
            ->whereNotNull("{$handoversTable}.confirmed_at")
            ->whereNull("{$handoversTable}.rejected_at")
            ->whereNotNull("{$assetsTable}.user_id")
            ->whereColumn("{$handoversTable}.recipient_user_id", "{$assetsTable}.user_id")
            ->whereDoesntHave('assetReturns')
            ->orderByDesc("{$handoversTable}.confirmed_at")
            ->orderByDesc("{$handoversTable}.id")
            ->get()
            ->unique('asset_id')
            ->keyBy(static fn (Handover $handover): int => (int) $handover->asset_id);
    }

    public function forAsset(Asset $asset): ?Handover
    {
        if ($asset->user_id === null || $asset->id === null) {
            return null;
        }

        return $this->forAssetIds([(int) $asset->id])->get((int) $asset->id);
    }
}
