<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Illuminate\Support\Collection;

/**
 * Assets, bei denen die Listen-Logik zugleich „Übergeben“ und „Rückgabe“ anbieten würde.
 */
final class ConflictingHandoverActionReporter
{
    public const PatternOwnerMismatch = 'owner_mismatch';

    public const PatternSameRecipient = 'same_recipient_open_and_confirmed';

    public const PatternInStockWithReturnable = 'in_stock_with_returnable_confirmed';

    public const PatternOther = 'other';

    public function __construct(
        private ReturnInitiatableHandoverResolver $returnResolver,
    ) {}

    /**
     * @return Collection<int, array{
     *     asset_id: int,
     *     serial_number: string|null,
     *     model: string|null,
     *     display_name: string,
     *     user_id: int|null,
     *     is_in_stock: bool,
     *     location: string|null,
     *     open_handover_id: int|null,
     *     open_recipient_user_id: int|null,
     *     open_created_at: string|null,
     *     returnable_handover_id: int|null,
     *     returnable_recipient_user_id: int|null,
     *     returnable_confirmed_at: string|null,
     *     returnable_has_completed_return: bool,
     *     returnable_is_superseded: bool,
     *     pattern: string
     * }>
     */
    public function rows(): Collection
    {
        $assets = Asset::query()
            ->where('is_missing', false)
            ->whereDoesntHave('handovers', function ($query): void {
                $query
                    ->whereNull('superseded_at')
                    ->whereHas('assetReturns', fn ($q) => $q->whereNull('completed_at'));
            })
            ->where(function ($query): void {
                $query
                    ->where('is_in_stock', true)
                    ->orWhere(function ($owned): void {
                        $owned
                            ->whereNotNull('user_id')
                            ->whereHas('handovers', fn ($q) => $q->open());
                    });
            })
            ->with([
                'handovers' => fn ($q) => $q->orderByDesc('id'),
                'handovers.assetReturns',
            ])
            ->orderBy('id')
            ->get();

        if ($assets->isEmpty()) {
            return collect();
        }

        $returnableByAssetId = $this->returnResolver->forAssetIds($assets->pluck('id'));
        $pendingReturnsByAssetId = collect(); // pending returns already excluded above
        $openByAssetId = Handover::query()
            ->open()
            ->whereIn('asset_id', $assets->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->unique('asset_id')
            ->keyBy('asset_id');

        return $assets
            ->filter(function (Asset $asset) use ($pendingReturnsByAssetId, $openByAssetId, $returnableByAssetId): bool {
                $canHandover = AdminHandoverEligibility::isEligibleForListeRow(
                    $asset,
                    $pendingReturnsByAssetId,
                    $openByAssetId,
                );

                return $canHandover && $returnableByAssetId->has($asset->id);
            })
            ->map(function (Asset $asset) use ($openByAssetId, $returnableByAssetId): array {
                $open = $openByAssetId->get($asset->id);
                $returnable = $returnableByAssetId->get($asset->id);

                return [
                    'asset_id' => $asset->id,
                    'serial_number' => $asset->serial_number,
                    'model' => $asset->model,
                    'display_name' => $asset->display_name,
                    'user_id' => $asset->user_id !== null ? (int) $asset->user_id : null,
                    'is_in_stock' => (bool) $asset->is_in_stock,
                    'location' => $asset->location,
                    'open_handover_id' => $open?->id,
                    'open_recipient_user_id' => $open?->recipient_user_id !== null ? (int) $open->recipient_user_id : null,
                    'open_created_at' => $open?->created_at?->toDateTimeString(),
                    'returnable_handover_id' => $returnable?->id,
                    'returnable_recipient_user_id' => $returnable?->recipient_user_id !== null ? (int) $returnable->recipient_user_id : null,
                    'returnable_confirmed_at' => $returnable?->confirmed_at?->toDateTimeString(),
                    'returnable_has_completed_return' => $returnable !== null
                        && $returnable->relationLoaded('assetReturns')
                            ? $returnable->assetReturns->whereNotNull('completed_at')->isNotEmpty()
                            : false,
                    'returnable_is_superseded' => $returnable?->superseded_at !== null,
                    'pattern' => $this->classify($asset, $open, $returnable),
                ];
            })
            ->values();
    }

    private function classify(Asset $asset, ?Handover $open, ?Handover $returnable): string
    {
        if ($asset->is_in_stock) {
            return self::PatternInStockWithReturnable;
        }

        if ($open === null || $returnable === null) {
            return self::PatternOther;
        }

        $openRecipient = $open->recipient_user_id !== null ? (int) $open->recipient_user_id : null;
        $returnableRecipient = $returnable->recipient_user_id !== null ? (int) $returnable->recipient_user_id : null;
        $ownerId = $asset->user_id !== null ? (int) $asset->user_id : null;

        if (
            $openRecipient !== null
            && $returnableRecipient !== null
            && $openRecipient === $returnableRecipient
        ) {
            return self::PatternSameRecipient;
        }

        if (
            $ownerId !== null
            && $openRecipient === $ownerId
            && $returnableRecipient !== null
            && $returnableRecipient !== $ownerId
        ) {
            return self::PatternOwnerMismatch;
        }

        return self::PatternOther;
    }
}
