<?php

namespace Hwkdo\IntranetAppAssets;

use Hwkdo\IntranetAppAssets\Commands\DomainCheckCommand;
use Hwkdo\IntranetAppAssets\Commands\BackfillOwnerHandoversCommand;
use Hwkdo\IntranetAppAssets\Commands\SetDomainConnectionCommand;
use Hwkdo\IntranetAppAssets\Commands\SyncLegacyAssetsCommand;
use Hwkdo\IntranetAppAssets\Contracts\IntuneDeviceLookupInterface;
use Hwkdo\IntranetAppAssets\Contracts\OrderNumberValidationServiceInterface;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetNote;
use Hwkdo\IntranetAppAssets\Observers\AssetObserver;
use Hwkdo\IntranetAppAssets\Observers\AssetHistoryObserver;
use Hwkdo\IntranetAppAssets\Observers\AssetNoteObserver;
use Hwkdo\IntranetAppAssets\Services\LegacyOrderNumberValidationService;
use Hwkdo\IntranetAppAssets\Support\MsGraphIntuneDeviceLookup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
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
                Commands\ItexiaImageSyncCommand::class,
                Commands\SyncConfigmgrDataCommand::class,
                Commands\SetItexiaIdsCommand::class,
                Commands\InvoiceAutoResolveCommand::class,
                BackfillOwnerHandoversCommand::class,
            ]);
    }

    public function boot(): void
    {
        parent::boot();

        Asset::observe(AssetObserver::class);
        AssetHistory::observe(AssetHistoryObserver::class);
        AssetNote::observe(AssetNoteObserver::class);

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

        $this->configureTypesenseIndexSettings();
    }

    protected function configureTypesenseIndexSettings(): void
    {
        $modelSettings = Config::get('scout.typesense.model-settings', []);

        $modelSettings[Asset::class] = [
            'collection-schema' => [
                'fields' => [
                    ['name' => 'id', 'type' => 'string'],
                    ['name' => 'name', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'model', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'location', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'owner_name', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'owner_vorname', 'type' => 'string', 'optional' => true],
                    ['name' => 'owner_nachname', 'type' => 'string', 'optional' => true],
                    ['name' => 'type_name', 'type' => 'string', 'optional' => true],
                    ['name' => 'vendor_name', 'type' => 'string', 'optional' => true],
                    ['name' => 'serial_number', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'imei', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'itexia_id', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'itexia_uuid', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'intune_device_id', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'configmgr_serial_number', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'smbios_guid', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'order_number', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'invoice_number', 'type' => 'string', 'optional' => true, 'infix' => true],
                    ['name' => 'domain_connection', 'type' => 'string', 'optional' => true],
                    ['name' => 'configmgr_last_logon_user', 'type' => 'string', 'optional' => true],
                    ['name' => 'status_tokens', 'type' => 'string', 'optional' => true],
                    ['name' => 'history_text', 'type' => 'string', 'optional' => true],
                    ['name' => 'notes_text', 'type' => 'string', 'optional' => true],
                    ['name' => 'created_at', 'type' => 'int64'],
                ],
                'default_sorting_field' => 'created_at',
            ],
            'search-parameters' => [
                'query_by' => implode(',', [
                    'serial_number',
                    'imei',
                    'itexia_id',
                    'itexia_uuid',
                    'intune_device_id',
                    'configmgr_serial_number',
                    'smbios_guid',
                    'name',
                    'model',
                    'owner_name',
                    'owner_vorname',
                    'owner_nachname',
                    'type_name',
                    'vendor_name',
                    'location',
                    'order_number',
                    'invoice_number',
                    'domain_connection',
                    'configmgr_last_logon_user',
                    'status_tokens',
                    'history_text',
                    'notes_text',
                ]),
                'prefix' => true,
            ],
        ];

        Config::set('scout.typesense.model-settings', $modelSettings);
    }
}
