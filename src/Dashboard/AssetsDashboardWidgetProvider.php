<?php

namespace Hwkdo\IntranetAppAssets\Dashboard;

use Hwkdo\IntranetAppBase\Data\DashboardWidgetDefinition;
use Hwkdo\IntranetAppBase\Interfaces\DashboardWidgetProviderInterface;

class AssetsDashboardWidgetProvider implements DashboardWidgetProviderInterface
{
    public static function widgets(): array
    {
        return [
            new DashboardWidgetDefinition(
                key: 'unbestaetigte-uebergaben',
                title: 'Unbestätigte Übergaben',
                description: 'Eigene Assets mit noch unbestätigter Übergabe',
                component: 'intranet-app-assets::apps.assets.widgets.unbestaetigte-uebergaben',
                defaultW: 12,
                defaultH: 4,
                minW: 6,
                minH: 3,
                defaultEnabled: true,
            ),
            new DashboardWidgetDefinition(
                key: 'vermisste-assets',
                title: 'Vermisste Assets',
                description: 'Assets mit Status Vermisst',
                component: 'intranet-app-assets::apps.assets.widgets.vermisste-assets',
                permission: 'manage-app-assets',
                defaultW: 6,
                defaultH: 4,
                minW: 4,
                minH: 3,
                defaultEnabled: true,
            ),
            new DashboardWidgetDefinition(
                key: 'assets-in-klaerung',
                title: 'Assets in Klärung',
                description: 'Assets mit offenem Klärungsbedarf',
                component: 'intranet-app-assets::apps.assets.widgets.assets-in-klaerung',
                permission: 'manage-app-assets',
                defaultW: 6,
                defaultH: 4,
                minW: 4,
                minH: 3,
                defaultEnabled: true,
            ),
            new DashboardWidgetDefinition(
                key: 'abgelehnte-uebergaben',
                title: 'Abgelehnte Übergaben',
                description: 'Abgelehnte Übergaben mit Klärungsbedarf',
                component: 'intranet-app-assets::apps.assets.widgets.abgelehnte-uebergaben',
                permission: 'manage-app-assets',
                defaultW: 6,
                defaultH: 4,
                minW: 4,
                minH: 3,
                defaultEnabled: true,
            ),
            new DashboardWidgetDefinition(
                key: 'fehlende-rechnungsnr',
                title: 'Fehlende Rechnungsnr',
                description: 'Assets mit fehlender Rechnungsnummer',
                component: 'intranet-app-assets::apps.assets.widgets.fehlende-rechnungsnr',
                permission: 'manage-app-assets',
                defaultW: 6,
                defaultH: 4,
                minW: 4,
                minH: 3,
                defaultEnabled: true,
            ),
            new DashboardWidgetDefinition(
                key: 'fehlend-in-itexia',
                title: 'Fehlend in Itexia',
                description: 'Assets mit Itexia-ID ohne Itexia-UUID',
                component: 'intranet-app-assets::apps.assets.widgets.fehlend-in-itexia',
                permission: 'manage-app-assets',
                defaultW: 6,
                defaultH: 4,
                minW: 4,
                minH: 3,
                defaultEnabled: true,
            ),
        ];
    }
}
