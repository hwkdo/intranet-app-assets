<?php

namespace Hwkdo\IntranetAppAssets\Tasks;

use Hwkdo\IntranetAppAssets\IntranetAppAssets;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class OffeneUebergabenTaskProvider implements TaskProviderInterface
{
    /**
     * One TaskItem per unconfirmed handover where the user is the recipient.
     *
     * @return Collection<int, TaskItem>
     */
    public function getTasksForUser(Authenticatable $user): Collection
    {
        $userId = $user->getAuthIdentifier();

        return Handover::query()
            ->with(['asset.type', 'asset.vendor'])
            ->whereHas('asset')
            ->where('recipient_user_id', $userId)
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Handover $handover) => new TaskItem(
                title: 'Übergabe bestätigen',
                url: route('apps.assets.handover.confirm', $handover),
                appIdentifier: IntranetAppAssets::identifier(),
                appName: IntranetAppAssets::app_name(),
                appIcon: IntranetAppAssets::app_icon(),
                description: self::taskDescription($handover),
                badge: 'Offen',
                priority: 5,
            ));
    }

    public function getLabel(): string
    {
        return 'Offene Asset-Übergaben';
    }

    private static function taskDescription(Handover $handover): string
    {
        $asset = $handover->asset;
        if ($asset === null) {
            return 'Asset nicht gefunden · Übergabe #'.$handover->id;
        }

        $name = $asset->display_name;
        $serial = filled($asset->serial_number) ? $asset->serial_number : '—';

        return $name.' · SN '.$serial;
    }
}
