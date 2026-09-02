<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Tasks;

use Hwkdo\IntranetAppAssets\IntranetAppAssets;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Support\AssetReturnSchedulePresenter;
use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class ScheduledAssetReturnsAdminTaskProvider implements TaskProviderInterface
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
            ->futureScheduled()
            ->with(['handover.asset.type', 'handover.asset.vendor'])
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get()
            ->filter(fn (AssetReturn $return) => $return->handover !== null)
            ->map(fn (AssetReturn $return) => new TaskItem(
                title: 'Geplante Rückgabe',
                url: route('apps.assets.admin.return.complete', $return),
                appIdentifier: IntranetAppAssets::identifier(),
                appName: IntranetAppAssets::app_name(),
                appIcon: IntranetAppAssets::app_icon(),
                description: ($return->handover?->asset?->display_name ?? 'Asset')
                    .' · '.($return->handover?->asset?->serial_number ?? '—')
                    .' · Termin '.(AssetReturnSchedulePresenter::formattedScheduledAt($return->scheduled_at) ?? '—'),
                badge: 'Geplant',
                priority: 5,
            ));
    }

    public function getLabel(): string
    {
        return 'Geplante Rückgaben (Admin)';
    }
}
