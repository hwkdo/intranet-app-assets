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

    protected $description = 'Synchronisiert SMBIOS-GUID, MAC-Adressen, Last-Logon-User und BIOS-Seriennummer aus ConfigMgr/SCCM für alle Assets mit Domain-Typ.';

    public function handle(): int
    {
        if (! class_exists(ConfigmgrLaravel::class)) {
            $this->warn('Das ConfigMgr-Paket ('.ConfigmgrLaravel::class.') ist nicht verfügbar.');

            return self::FAILURE;
        }

        $configmgr = app(ConfigmgrLaravel::class);
        $verbose = $this->option('details');

        try {
            $biosSerialsByName = $configmgr->getBiosSerialNumbersByComputerName();
        } catch (\Throwable $e) {
            $this->error('BIOS-Seriennummern aus ConfigMgr konnten nicht gelesen werden: '.$e->getMessage());

            return self::FAILURE;
        }

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

            $biosSerial = $biosSerialsByName[strtoupper($computerName)] ?? null;

            if (count($rows) === 0) {
                $asset->smbios_guid = null;
                $asset->configmgr_mac_addresses = null;
                $asset->configmgr_last_logon_user = null;
                $asset->configmgr_serial_number = $biosSerial;
                $asset->save();
                $updated++;
                if ($verbose) {
                    $serialInfo = $biosSerial !== null ? "BIOS-Seriennummer {$biosSerial}" : 'BIOS-Seriennummer leer';
                    $this->line("  Asset #{$asset->id} ({$computerName}): keine ConfigMgr-Systemdaten – GUID/MAC/Logon geleert, {$serialInfo}.");
                }

                continue;
            }

            $first = $rows[0];
            $smbiosGuid = $first->smbios_guid ?? null;
            $macAddresses = array_values(array_unique(array_filter(array_map(fn ($row) => $row->mac_adresse ?? null, $rows))));
            $lastLogonUser = $first->last_logon_user ?? null;

            $asset->smbios_guid = $smbiosGuid;
            $asset->configmgr_mac_addresses = $macAddresses;
            $asset->configmgr_last_logon_user = $lastLogonUser;
            $asset->configmgr_serial_number = $biosSerial;
            $asset->save();
            $updated++;
            if ($verbose) {
                $serialInfo = $biosSerial !== null ? $biosSerial : 'keine';
                $this->line("  Asset #{$asset->id} ({$computerName}): SMBIOS-GUID, ".count($macAddresses)." MAC(s), Last-Logon-User, BIOS-Seriennummer ({$serialInfo}) gesetzt.");
            }
        }

        $this->info("  → {$updated} Assets verarbeitet, {$skipped} übersprungen.");

        return self::SUCCESS;
    }
}
