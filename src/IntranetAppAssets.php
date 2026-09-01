<?php

namespace Hwkdo\IntranetAppAssets;

use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesDashboardWidgetsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesTasksInterface;
use Hwkdo\IntranetAppAssets\Mcp\Servers\AssetsServer;
use Hwkdo\IntranetAppAssets\Dashboard\AssetsDashboardWidgetProvider;
use Hwkdo\IntranetAppAssets\Tasks\PendingAssetReturnsAdminTaskProvider;
use Hwkdo\IntranetAppAssets\Tasks\FehlendeRechnungsnrTaskProvider;
use Hwkdo\IntranetAppAssets\Tasks\OffeneUebergabenTaskProvider;
use Illuminate\Support\Collection;

class IntranetAppAssets implements IntranetAppInterface, ProvidesTasksInterface, ProvidesDashboardWidgetsInterface
{
    public static function app_name(): string
    {
        return 'Assets';
    }

    public static function app_icon(): string
    {
        return 'magnifying-glass';
    }

    public static function identifier(): string
    {
        return 'assets';
    }

    public static function roles_admin(): Collection
    {
        return collect(config('intranet-app-assets.roles.admin'));
    }

    public static function roles_user(): Collection
    {
        return collect(config('intranet-app-assets.roles.user'));
    }

    public static function userSettingsClass(): ?string
    {
        return \Hwkdo\IntranetAppAssets\Data\UserSettings::class;
    }

    public static function appSettingsClass(): ?string
    {
        return \Hwkdo\IntranetAppAssets\Data\AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [
            'assets' => [
                'class' => AssetsServer::class,
                'middleware' => ['auth:api'],
            ],
        ];
    }

    public static function taskProviders(): array
    {
        return [
            OffeneUebergabenTaskProvider::class,
            FehlendeRechnungsnrTaskProvider::class,
            PendingAssetReturnsAdminTaskProvider::class,
        ];
    }

    public static function dashboardWidgetProviders(): array
    {
        return [
            AssetsDashboardWidgetProvider::class,
        ];
    }
}
