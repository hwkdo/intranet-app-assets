<?php

namespace Hwkdo\IntranetAppAssets\Tasks;

use Hwkdo\IntranetAppAssets\IntranetAppAssets;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class PendingAssetReturnsAdminTaskProvider implements TaskProviderInterface
{
    /**
     * @return Collection<int, TaskItem>
     */
    public function getTasksForUser(Authenticatable $user): Collection
    {
        if (! $user->can('manage-app-assets')) {
            return collect();
        }

        return AssetReturn::query()
            ->whereNull('completed_at')
            ->with(['handover.asset.type', 'handover.asset.vendor'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->filter(fn (AssetReturn $r) => $r->handover !== null)
            ->map(fn (AssetReturn $return) => new TaskItem(
                title: 'Offene Rückgabe',
                url: route('apps.assets.admin.return.complete', $return),
                appIdentifier: IntranetAppAssets::identifier(),
                appName: IntranetAppAssets::app_name(),
                appIcon: IntranetAppAssets::app_icon(),
                description: ($return->handover?->asset?->display_name ?? 'Asset').' · '.($return->handover?->asset?->serial_number ?? '—'),
                badge: 'Rückgabe',
                priority: 6,
            ));
    }

    public function getLabel(): string
    {
        return 'Offene Rückgaben (Admin)';
    }
}
