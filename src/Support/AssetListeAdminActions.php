<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;

/**
 * Zustandsbezogene Admin-Aktionen für die „Alle Assets“-Listenzeile.
 */
class AssetListeAdminActions
{
    /**
     * @param  array{
     *     pending_return?: AssetReturn|null,
     *     open_handover?: Handover|null,
     *     rejected_handover?: Handover|null,
     * }  $related
     * @return list<array{key: string, label: string, href: string, icon: string}>
     */
    public static function resolveLinks(Asset $asset, array $related = []): array
    {
        $actions = [];

        $pendingReturn = $related['pending_return'] ?? null;
        if ($pendingReturn instanceof AssetReturn) {
            $actions[] = [
                'key' => 'pending_return',
                'label' => 'Offene Rückgabe bearbeiten',
                'href' => route('apps.assets.admin.return.complete', $pendingReturn),
                'icon' => 'inbox-arrow-down',
            ];
        }

        $openHandover = $related['open_handover'] ?? null;
        if ($openHandover instanceof Handover) {
            $actions[] = [
                'key' => 'open_handover',
                'label' => 'Offene Übergabe bearbeiten',
                'href' => route('apps.assets.admin.open-handover.resolve', $openHandover),
                'icon' => 'clock',
            ];
        }

        $rejectedHandover = $related['rejected_handover'] ?? null;
        if ($rejectedHandover instanceof Handover) {
            $actions[] = [
                'key' => 'rejected_handover',
                'label' => 'Abgelehnte Übergabe bearbeiten',
                'href' => route('apps.assets.admin.rejected-handover.resolve', $rejectedHandover),
                'icon' => 'x-circle',
            ];
        }

        if ($asset->is_clarification) {
            $actions[] = [
                'key' => 'clarification',
                'label' => 'Klärungsfall bearbeiten',
                'href' => route('apps.assets.admin.clarification.resolve', $asset),
                'icon' => 'pencil-square',
            ];
        }

        if ($asset->is_missing) {
            $actions[] = [
                'key' => 'missing',
                'label' => 'Vermisst-Fall bearbeiten',
                'href' => route('apps.assets.admin.missing.resolve', $asset),
                'icon' => 'check-circle',
            ];
        }

        return $actions;
    }
}
