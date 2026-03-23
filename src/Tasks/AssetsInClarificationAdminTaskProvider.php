<?php

namespace Hwkdo\IntranetAppAssets\Tasks;

use Hwkdo\IntranetAppAssets\IntranetAppAssets;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class AssetsInClarificationAdminTaskProvider implements TaskProviderInterface
{
    /**
     * @return Collection<int, TaskItem>
     */
    public function getTasksForUser(Authenticatable $user): Collection
    {
        if (! $user->can('manage-app-assets')) {
            return collect();
        }

        return Asset::query()
            ->where('is_clarification', true)
            ->with(['type', 'vendor'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Asset $asset) => new TaskItem(
                title: 'Asset in Klärung',
                url: route('apps.assets.admin.clarification.resolve', $asset),
                appIdentifier: IntranetAppAssets::identifier(),
                appName: IntranetAppAssets::app_name(),
                appIcon: IntranetAppAssets::app_icon(),
                description: $asset->display_name.' · '.$asset->serial_number,
                badge: 'Klären',
                priority: 6,
            ));
    }

    public function getLabel(): string
    {
        return 'Assets in Klärung (Admin)';
    }
}
