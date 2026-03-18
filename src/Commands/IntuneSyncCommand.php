<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Carbon\Carbon;
use Hwkdo\IntranetAppAssets\Contracts\IntuneDeviceLookupInterface;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Illuminate\Console\Command;

class IntuneSyncCommand extends Command
{
    protected $signature = 'intranet-app-assets:intune-sync
                            {--details : Pro Asset ausgeben, ob in Intune gefunden und ID gesetzt}';

    protected $description = 'Findet Intune-Geräte anhand der Seriennummer und aktualisiert Intune-Geräte-ID, IMEI und Last Check-in für alle Intune-Typ-Assets.';

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
            ->whereNotNull('serial_number')
            ->where('serial_number', '!=', '')
            ->get();

        $this->info('Prüfe '.$assets->count().' Assets mit Intune-Typ (Seriennummer gesetzt)…');

        $updated = 0;
        foreach ($assets as $asset) {
            $serial = trim($asset->serial_number ?? '');

            if ($serial === '') {
                if ($verbose) {
                    $this->line("  Asset #{$asset->id}: Seriennummer leer – übersprungen.");
                }

                continue;
            }

            $device = $intune->findDeviceBySerialNumber($serial);

            if ($device !== null) {
                $hadNoIntuneId = empty($asset->intune_device_id);
                $asset->intune_device_id = $device['id'];
                $asset->imei = $device['imei'] ?? $asset->imei;
                $asset->intune_last_check_in = isset($device['lastSyncDateTime']) && $device['lastSyncDateTime'] !== null && $device['lastSyncDateTime'] !== ''
                    ? Carbon::parse($device['lastSyncDateTime'])
                    : null;
                $asset->save();
                if ($hadNoIntuneId) {
                    $asset->historyEntries()->create([
                        'event' => AssetHistory::EventUpdated,
                        'user_id' => null,
                    ]);
                }
                $updated++;
                if ($verbose) {
                    $imeiInfo = ($device['imei'] ?? null) !== null && ($device['imei'] ?? '') !== '' ? ', IMEI gespeichert' : '';
                    $checkInInfo = $asset->intune_last_check_in !== null ? ', Last Check-in aktualisiert' : '';
                    $this->line("  Asset #{$asset->id} (SN: {$serial}): in Intune gefunden → ID gespeichert{$imeiInfo}{$checkInInfo}.");
                }
            } elseif ($verbose) {
                $this->line("  Asset #{$asset->id} (SN: {$serial}): nicht in Intune gefunden.");
            }
        }

        $this->info("  → {$updated} Asset(s) aktualisiert (Intune-Geräte-ID, ggf. IMEI und Last Check-in).");

        return self::SUCCESS;
    }
}
