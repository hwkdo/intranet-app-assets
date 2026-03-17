<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\ConfigmgrLaravel\ConfigmgrLaravel;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Illuminate\Console\Command;

class SyncConfigmgrDataCommand extends Command
{
    protected $signature = 'intranet-app-assets:sync-configmgr-data
                            {--details : Pro Asset ausgeben, ob ConfigMgr-Daten gefunden und gespeichert wurden}';

    protected $description = 'Synchronisiert SMBIOS-GUID und MAC-Adressen aus ConfigMgr/SCCM für alle Assets mit Domain-Typ.';

    public function handle(): int
    {
        if (! class_exists(ConfigmgrLaravel::class)) {
            $this->warn('Das ConfigMgr-Paket ('.ConfigmgrLaravel::class.') ist nicht verfügbar.');

            return self::FAILURE;
        }

        $configmgr = app(ConfigmgrLaravel::class);
        $verbose = $this->option('details');

        $domainTypeIds = AssetType::where('is_domain_object', true)->pluck('id');
        $assets = Asset::whereIn('asset_type_id', $domainTypeIds)->get();

        $this->info('Synchronisiere ConfigMgr-Daten für '.$assets->count().' Assets mit Domain-Typ…');

        $updated = 0;
        $skipped = 0;

        foreach ($assets as $asset) {
            $computerName = trim((string) ($asset->name ?? ''));
            if ($computerName === '') {
                if ($verbose) {
                    $this->line("  Asset #{$asset->id}: Name leer – übersprungen.");
                }
                $skipped++;

                continue;
            }

            try {
                $rows = $configmgr->getSystemDataByComputerName($computerName);
            } catch (\Throwable $e) {
                if ($verbose) {
                    $this->line("  Asset #{$asset->id} ({$computerName}): Fehler – ".$e->getMessage());
                }
                $skipped++;

                continue;
            }

            if (count($rows) === 0) {
                $asset->smbios_guid = null;
                $asset->configmgr_mac_addresses = null;
                $asset->save();
                $updated++;
                if ($verbose) {
                    $this->line("  Asset #{$asset->id} ({$computerName}): keine ConfigMgr-Daten – Felder geleert.");
                }

                continue;
            }

            $first = $rows[0];
            $smbiosGuid = $first->smbios_guid ?? null;
            $macAddresses = array_values(array_unique(array_filter(array_map(fn ($row) => $row->mac_adresse ?? null, $rows))));

            $asset->smbios_guid = $smbiosGuid;
            $asset->configmgr_mac_addresses = $macAddresses;
            $asset->save();
            $updated++;
            if ($verbose) {
                $this->line("  Asset #{$asset->id} ({$computerName}): SMBIOS-GUID gesetzt, ".count($macAddresses).' MAC-Adresse(n).');
            }
        }

        $this->info("  → {$updated} Assets verarbeitet, {$skipped} übersprungen.");

        return self::SUCCESS;
    }
}
