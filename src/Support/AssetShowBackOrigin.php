<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Kontext für „Zurück“ von der Asset-Detailseite: Query `from` validieren und Ziel-URL/Label liefern.
 */
final class AssetShowBackOrigin
{
    /** @var array<string, array{route: string, label: string, manage: bool}> */
    private const ORIGINS = [
        'meine-assets' => ['route' => 'apps.assets.meine-assets', 'label' => 'Meine Assets', 'manage' => false],
        'index' => ['route' => 'apps.assets.index', 'label' => 'Übersicht', 'manage' => false],
        'liste' => ['route' => 'apps.assets.liste', 'label' => 'Alle Assets', 'manage' => true],
        'itexiageraete' => ['route' => 'apps.assets.itexiageraete', 'label' => 'Itexia-Geräte', 'manage' => true],
        'mobilgeraete' => ['route' => 'apps.assets.mobilgeraete', 'label' => 'Mobilgeräte', 'manage' => true],
        'domaenengeraete' => ['route' => 'apps.assets.domaenengeraete', 'label' => 'Domänengeräte', 'manage' => true],
        'search' => ['route' => 'apps.assets.search', 'label' => 'Suche', 'manage' => true],
        'chat' => ['route' => 'apps.assets.chat', 'label' => 'Chat', 'manage' => true],
        'domain-compare' => ['route' => 'apps.assets.domain-compare', 'label' => 'Domain-Abgleich', 'manage' => true],
        'sccm-compare' => ['route' => 'apps.assets.sccm-compare', 'label' => 'SCCM-Abgleich', 'manage' => true],
        'deleted' => ['route' => 'apps.assets.deleted', 'label' => 'Gelöschte Assets', 'manage' => true],
        'fehlende-rechnung' => ['route' => 'apps.assets.fehlende-rechnung', 'label' => 'Fehlende Rechnung', 'manage' => true],
        'legacy-assets' => ['route' => 'apps.assets.legacy-assets', 'label' => 'Legacy-Assets', 'manage' => true],
        'rechnungen' => ['route' => 'apps.assets.rechnungen', 'label' => 'Rechnungen Analyse', 'manage' => true],
        'admin-handovers' => ['route' => 'apps.assets.admin.open-handovers', 'label' => 'Unbestätigte Übergaben', 'manage' => true],
    ];

    /**
     * @return array{key: string, href: string, label: string, buttonLabel: string}
     */
    public static function resolve(?string $from, ?Authenticatable $user, ?string $searchReturnQuery = null): array
    {
        $from = $from !== null ? trim($from) : '';
        $from = $from === '' ? null : $from;

        $canManage = $user !== null && Gate::forUser($user)->allows('manage-app-assets');

        $key = self::validatedKey($from, $canManage);
        $meta = self::ORIGINS[$key];

        $href = route($meta['route']);
        if ($key === 'search') {
            $q = self::sanitizeSearchReturnQuery($searchReturnQuery);
            if ($q !== null) {
                $href = route($meta['route'], ['q' => $q]);
            }
        }

        return [
            'key' => $key,
            'href' => $href,
            'label' => $meta['label'],
            'buttonLabel' => 'Zurück zu '.$meta['label'],
        ];
    }

    private static function sanitizeSearchReturnQuery(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $q = trim($value);
        if ($q === '') {
            return null;
        }
        if (mb_strlen($q) > 512) {
            $q = mb_substr($q, 0, 512);
        }

        return $q;
    }

    private static function validatedKey(?string $from, bool $canManage): string
    {
        if ($from !== null && isset(self::ORIGINS[$from])) {
            $needsManage = self::ORIGINS[$from]['manage'];
            if (! $needsManage || $canManage) {
                return $from;
            }
        }

        return $canManage ? 'liste' : 'meine-assets';
    }
}
