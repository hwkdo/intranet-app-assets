<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Livewire\Concerns;

trait RefreshesOnAssetsDashboardBroadcast
{
    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $user = auth()->user();

        if ($user === null || ! $user->can('manage-app-assets')) {
            return [];
        }

        return [
            'echo-private:assets-dashboard-channel,.assets.dashboard-changed' => 'refreshFromAssetsDashboardBroadcast',
        ];
    }

    public function refreshFromAssetsDashboardBroadcast(): void
    {
        unset(
            $this->assets,
            $this->handovers,
            $this->returns,
            $this->mismatches,
            $this->hasMore,
            $this->totalCount,
        );
    }
}
