<?php

namespace Hwkdo\IntranetAppAssets;

use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppAssets\Ai\Gateway\OpenWebUiChatGateway;
use Hwkdo\IntranetAppAssets\Ai\Providers\OpenWebUiChatProvider;
use Hwkdo\IntranetAppAssets\Commands\BackfillOwnerHandoversCommand;
use Hwkdo\IntranetAppAssets\Commands\DomainCheckCommand;
use Hwkdo\IntranetAppAssets\Commands\EnsureAssetHandoversCommand;
use Hwkdo\IntranetAppAssets\Commands\SetDomainConnectionCommand;
use Hwkdo\IntranetAppAssets\Commands\SyncLegacyAssetsCommand;
use Hwkdo\IntranetAppAssets\Contracts\IntuneDeviceLookupInterface;
use Hwkdo\IntranetAppAssets\Contracts\OrderNumberValidationServiceInterface;
use Hwkdo\IntranetAppAssets\Listeners\UpdateCachedItexiaActualRoom;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetNote;
use Hwkdo\IntranetAppAssets\Observers\AssetHistoryObserver;
use Hwkdo\IntranetAppAssets\Observers\AssetNoteObserver;
use Hwkdo\IntranetAppAssets\Observers\AssetObserver;
use Hwkdo\IntranetAppAssets\Observers\AssetOwnerHandoverObserver;
use Hwkdo\IntranetAppAssets\Services\AssetOwnerHandoverAutomationService;
use Hwkdo\IntranetAppAssets\Services\AssetPermanentDeletionArchiveRecorder;
use Hwkdo\IntranetAppAssets\Services\LegacyOrderNumberValidationService;
use Hwkdo\IntranetAppAssets\Support\MsGraphIntuneDeviceLookup;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphIntuneServiceInterface;
use Hwkdo\SeventhingsLaravel\Events\ItexiaAssetActualRoomUpdated;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\AiManager;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IntranetAppAssetsServiceProvider extends PackageServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(AssetPermanentDeletionArchiveRecorder::class);
        $this->app->singleton(AssetOwnerHandoverAutomationService::class);

        if (class_exists(IntranetLegacyService::class)) {
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
                Commands\ItexiaRoomsSyncCommand::class,
                Commands\ItexiaImageSyncCommand::class,
                Commands\SyncConfigmgrDataCommand::class,
                Commands\SetItexiaIdsCommand::class,
                Commands\InvoiceAutoResolveCommand::class,
                BackfillOwnerHandoversCommand::class,
                EnsureAssetHandoversCommand::class,
            ]);
    }

    public function boot(): void
    {
        parent::boot();

        Asset::observe(AssetObserver::class);
        Asset::observe(AssetOwnerHandoverObserver::class);
        AssetHistory::observe(AssetHistoryObserver::class);
        AssetNote::observe(AssetNoteObserver::class);

        Event::listen(ItexiaAssetActualRoomUpdated::class, UpdateCachedItexiaActualRoom::class);

        $msgraphInterface = MsGraphIntuneServiceInterface::class;
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

        Livewire::addComponent(
            name: 'intranet-app-assets::apps.assets.chat',
            viewPath: __DIR__.'/../resources/views/livewire/apps/assets/chat.blade.php'
        );

        $this->app->booted(function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/ai.php');
        });

        $this->app->resolving(Schedule::class, function () {
            require __DIR__.'/../routes/console.php';
        });

        $this->configureTypesenseIndexSettings();

        $relayConfig = require __DIR__.'/../config/relay.php';
        $existingServers = config('relay.servers', []);
        config([
            'relay.servers' => array_merge($existingServers, $relayConfig['servers'] ?? []),
        ]);

        $this->registerOpenWebUiAiProvider();
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
                    ['name' => 'itexia_actual_room_id', 'type' => 'string', 'optional' => true],
                    ['name' => 'itexia_target_room_id', 'type' => 'string', 'optional' => true],
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
                    'itexia_actual_room_id',
                    'itexia_target_room_id',
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

    protected function registerOpenWebUiAiProvider(): void
    {
        if (! class_exists(AiManager::class)) {
            return;
        }

        $this->app->resolving(AiManager::class, function (AiManager $manager): void {
            $manager->extend('openwebui-assets-chat', function ($app, array $config) {
                return new OpenWebUiChatProvider(
                    gateway: new OpenWebUiChatGateway($app['events']),
                    config: $config,
                    events: $app['events'],
                );
            });
        });

        $providers = config('ai.providers', []);
        $providers['openwebui-assets-chat'] = array_merge([
            'driver' => 'openwebui-assets-chat',
            'key' => config('openwebui-api-laravel.api_key', ''),
            'url' => config('openwebui-api-laravel.base_api_url', ''),
            'models' => [
                'text' => [
                    'default' => config('openwebui-api-laravel.default_model', 'gpt-oss:20b'),
                ],
            ],
        ], $providers['openwebui-assets-chat'] ?? []);

        config(['ai.providers' => $providers]);
    }
}
