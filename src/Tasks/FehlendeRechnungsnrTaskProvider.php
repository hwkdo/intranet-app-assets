<?php

namespace Hwkdo\IntranetAppAssets\Tasks;

use Hwkdo\IntranetAppAssets\IntranetAppAssets;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class FehlendeRechnungsnrTaskProvider implements TaskProviderInterface
{
    /**
     * One TaskItem per asset created by the user that still has no invoice number (invoice_number_pending).
     *
     * @return Collection<int, TaskItem>
     */
    public function getTasksForUser(Authenticatable $user): Collection
    {
        $userId = $user->getAuthIdentifier();

        return Asset::query()
            ->invoiceNumberPending()
            ->where('created_by_user_id', $userId)
            ->with(['type', 'vendor'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Asset $asset) => new TaskItem(
                title: 'fehlende Rechnungsnr',
                url: route('apps.assets.edit', $asset),
                appIdentifier: IntranetAppAssets::identifier(),
                appName: IntranetAppAssets::app_name(),
                appIcon: IntranetAppAssets::app_icon(),
                description: $asset->display_name.' · '.$asset->serial_number,
                badge: 'Offen',
                priority: 4,
            ));
    }

    public function getLabel(): string
    {
        return 'Fehlende Rechnungsnr';
    }
}
