<?php

namespace Hwkdo\IntranetAppAssets;

use Hwkdo\IntranetAppAssets\Commands\DomainCheckCommand;
use Hwkdo\IntranetAppAssets\Commands\SetDomainConnectionCommand;
use Hwkdo\IntranetAppAssets\Commands\SyncLegacyAssetsCommand;
use Hwkdo\IntranetAppAssets\Contracts\IntuneDeviceLookupInterface;
use Hwkdo\IntranetAppAssets\Contracts\OrderNumberValidationServiceInterface;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Observers\AssetObserver;
use Hwkdo\IntranetAppAssets\Services\LegacyOrderNumberValidationService;
use Hwkdo\IntranetAppAssets\Support\MsGraphIntuneDeviceLookup;
use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IntranetAppAssetsServiceProvider extends PackageServiceProvider
{
    public function register(): void
    {
        parent::register();

        if (class_exists(\App\Services\IntranetLegacyService::class)) {
            $this->app->bind(
                OrderNumberValidationServiceInterface::class,
                LegacyOrderNumberValidationService::class
            );
        }
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('intranet-app-assets')
            ->hasConfigFile()
            ->hasViews()
            ->discoversMigrations()
            ->hasCommands([
                SyncLegacyAssetsCommand::class,
                SetDomainConnectionCommand::class,
                DomainCheckCommand::class,
                Commands\IntuneSyncCommand::class,
                Commands\ItexiaUuidSyncCommand::class,
                Commands\SyncConfigmgrDataCommand::class,
                Commands\SetItexiaIdsCommand::class,
            ]);
    }

    public function boot(): void
    {
        parent::boot();

        Asset::observe(AssetObserver::class);

        $msgraphInterface = \Hwkdo\MsGraphLaravel\Interfaces\MsGraphIntuneServiceInterface::class;
        if (interface_exists($msgraphInterface) && $this->app->bound($msgraphInterface)) {
            $this->app->bind(IntuneDeviceLookupInterface::class, MsGraphIntuneDeviceLookup::class);
        }

        Livewire::addNamespace(
            namespace: 'intranet-app-assets',
            viewPath: __DIR__.'/../resources/views/livewire',
            classNamespace: 'Hwkdo\IntranetAppAssets\Livewire',
            classPath: __DIR__.'/../src/Livewire',
            classViewPath: __DIR__.'/../resources/views/livewire',
        );

        $this->app->booted(function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        $this->app->resolving(Schedule::class, function () {
            require __DIR__.'/../routes/console.php';
        });
    }
}
