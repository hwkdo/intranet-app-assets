<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Search;

use Hwkdo\IntranetAppAssets\IntranetAppAssets;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppBase\Data\SearchResult;
use Hwkdo\IntranetAppBase\Interfaces\SearchSourceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class AssetsSearchSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'assets.assets';
    }

    public function label(): string
    {
        return 'Assets';
    }

    public function appIdentifier(): string
    {
        return IntranetAppAssets::identifier();
    }

    public function appName(): string
    {
        return IntranetAppAssets::app_name();
    }

    public function icon(): string
    {
        return IntranetAppAssets::app_icon();
    }

    public function isAvailableFor(Authenticatable $user): bool
    {
        if (! method_exists($user, 'can')) {
            return true;
        }

        return $user->can('see-app-'.$this->appIdentifier());
    }

    public function search(string $query, Authenticatable $user, int $limit): Collection
    {
        return AssetSearch::query($query, $user, $limit)
            ->map(fn (Asset $asset): SearchResult => new SearchResult(
                title: $asset->display_name,
                url: route('apps.assets.show', $asset),
                appIdentifier: $this->appIdentifier(),
                appName: $this->appName(),
                icon: $this->icon(),
                subtitle: $this->subtitle($asset),
                sourceKey: $this->key(),
            ))
            ->values();
    }

    private function subtitle(Asset $asset): ?string
    {
        $parts = array_values(array_filter([
            trim((string) ($asset->serial_number ?? '')),
            trim((string) ($asset->owner?->name ?? '')),
            $asset->type?->name,
        ], fn (?string $part): bool => $part !== null && $part !== ''));

        if ($parts === []) {
            return null;
        }

        return implode(' · ', array_slice($parts, 0, 2));
    }
}
