<?php

namespace Hwkdo\IntranetAppAssets\Tasks;

use Hwkdo\IntranetAppAssets\Enums\ReturnScheduleType;
use Hwkdo\IntranetAppAssets\IntranetAppAssets;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Support\AssetReturnSchedulePresenter;
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
            ->adminOpenTask()
            ->whereHas('handover')
            ->with(['handover.asset.type', 'handover.asset.vendor'])
            ->orderByRaw('CASE WHEN schedule_type = ? AND scheduled_at IS NOT NULL AND scheduled_at <= ? THEN 0 ELSE 1 END', [
                ReturnScheduleType::Scheduled->value,
                now(),
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (AssetReturn $return) => new TaskItem(
                title: $return->isLoan() ? 'Offene Leihe-Rückgabe' : 'Offene Rückgabe',
                url: route('apps.assets.admin.return.complete', $return),
                appIdentifier: IntranetAppAssets::identifier(),
                appName: IntranetAppAssets::app_name(),
                appIcon: IntranetAppAssets::app_icon(),
                description: ($return->handover?->asset?->display_name ?? 'Asset')
                    .' · '.($return->handover?->asset?->serial_number ?? '—')
                    .($return->isScheduled() ? ' · Termin '.(AssetReturnSchedulePresenter::formattedScheduledAt($return->scheduled_at) ?? '—') : ''),
                badge: $return->isLoan()
                    ? ($return->isOverdue() ? 'Leihe · Überfällig' : 'Leihe')
                    : ($return->isOverdue() ? 'Überfällig' : 'Rückgabe'),
                priority: $return->isOverdue() ? 7 : 6,
            ));
    }

    public function getLabel(): string
    {
        return 'Offene Rückgaben (Admin)';
    }
}
