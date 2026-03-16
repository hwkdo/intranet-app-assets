<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Contracts\IntuneDeviceLookupInterface;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Illuminate\Console\Command;

class IntuneSyncCommand extends Command
{
    protected $signature = 'intranet-app-assets:intune-sync
                            {--details : Pro Asset ausgeben, ob in Intune gefunden und ID gesetzt}';

    protected $description = 'Findet Intune-Geräte anhand der Seriennummer und speichert die Intune-Geräte-ID für Assets mit Intune-Typ, die noch keine ID haben.';

    public function handle(): int
    {
        if (! $this->laravel->bound(IntuneDeviceLookupInterface::class)) {
            $this->warn('Intune-Device-Lookup nicht registriert. Binde '.IntuneDeviceLookupInterface::class.' in der App (z. B. AppServiceProvider).');

            return self::FAILURE;
        }

        $intune = $this->laravel->make(IntuneDeviceLookupInterface::class);
        $verbose = $this->option('details');

        $intuneTypeIds = AssetType::where('is_intune_object', true)->pluck('id');
        $assets = Asset::whereIn('asset_type_id', $intuneTypeIds)
            ->where(function ($query) {
                $query->whereNull('intune_device_id')
                    ->orWhere('intune_device_id', '');
            })
            ->get();

        $this->info('Prüfe '.$assets->count().' Assets mit Intune-Typ ohne Geräte-ID…');

        $updated = 0;
        foreach ($assets as $asset) {
            $serial = $asset->serial_number;

            if (empty($serial)) {
                if ($verbose) {
                    $this->line("  Asset #{$asset->id}: Seriennummer leer – übersprungen.");
                }

                continue;
            }

            $device = $intune->findDeviceBySerialNumber($serial);

            if ($device !== null) {
                $asset->intune_device_id = $device['id'];
                $asset->imei = $device['imei'];
                $asset->save();
                $asset->historyEntries()->create([
                    'event' => AssetHistory::EventUpdated,
                    'user_id' => null,
                ]);
                $updated++;
                if ($verbose) {
                    $imeiInfo = $device['imei'] !== null && $device['imei'] !== '' ? ', IMEI gespeichert' : '';
                    $this->line("  Asset #{$asset->id} (SN: {$serial}): in Intune gefunden → ID gespeichert{$imeiInfo}.");
                }
            } elseif ($verbose) {
                $this->line("  Asset #{$asset->id} (SN: {$serial}): nicht in Intune gefunden.");
            }
        }

        $this->info("  → {$updated} Asset(s) mit Intune-Geräte-ID (und ggf. IMEI) aktualisiert.");

        return self::SUCCESS;
    }
}
