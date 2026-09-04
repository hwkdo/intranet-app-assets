<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;

final class AssetReturnSchedulePresenter
{
    public static function formattedScheduledAt(?CarbonInterface $scheduledAt): ?string
    {
        return $scheduledAt?->timezone(config('app.timezone'))->format('d.m.Y H:i');
    }

    /**
     * @return array{label: string, color: string}|null
     */
    public static function scheduleBadge(AssetReturn $return): ?array
    {
        if (! $return->isScheduled() || $return->isCompleted()) {
            return null;
        }

        $isLoan = $return->isLoan();

        if ($return->isOverdue()) {
            return [
                'label' => $isLoan ? 'Leihe · Überfällig' : 'Überfällig',
                'color' => 'red',
            ];
        }

        if ($return->scheduled_at?->isFuture()) {
            return [
                'label' => $isLoan ? 'Leihe' : 'Geplant',
                'color' => 'blue',
            ];
        }

        return null;
    }
}
