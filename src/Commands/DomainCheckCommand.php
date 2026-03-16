<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Contracts\LdapComputerServiceInterface;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Illuminate\Console\Command;

class DomainCheckCommand extends Command
{
    protected $signature = 'intranet-app-assets:domain-check
                            {--details : Pro Asset ausgeben, ob in LDAP gefunden und welche Werte gesetzt wurden}';

    protected $description = 'Aktualisiert Domain-Daten (Last Seen, Last Logon, Last Logon Timestamp) für alle Assets mit Domain-Typ und gesetzter Connection per LDAP.';

    public function handle(): int
    {
        if (! $this->laravel->bound(LdapComputerServiceInterface::class)) {
            $this->warn('LDAP-Computer-Service nicht registriert. Binde '.LdapComputerServiceInterface::class.' in der App (z. B. AppServiceProvider).');

            return self::FAILURE;
        }

        $ldap = $this->laravel->make(LdapComputerServiceInterface::class);
        $verbose = $this->option('details');

        $domainTypeIds = AssetType::where('is_domain_object', true)->pluck('id');
        $assets = Asset::whereIn('asset_type_id', $domainTypeIds)
            ->whereNotNull('domain_connection')
            ->get();

        $this->info('Prüfe '.$assets->count().' Assets mit Domain Connection…');

        $count = 0;
        foreach ($assets as $asset) {
            $hostname = $asset->name;
            $connection = $asset->domain_connection ?? 'default';

            if (empty($hostname)) {
                if ($verbose) {
                    $this->line("  Asset #{$asset->id}: Name leer – übersprungen.");
                }

                continue;
            }

            $exists = $ldap->exists($hostname, $connection);
            $now = now();

            if ($exists) {
                $asset->domain_last_seen = $now;
                $lastLogon = $ldap->getLastLogon($hostname, $connection);
                $asset->last_logon = $lastLogon ?: null;
                $lastLogonTs = $ldap->getLastLogonTimestamp($hostname, $connection);
                $asset->last_logon_timestamp = $lastLogonTs ?: null;
                if ($verbose) {
                    $this->line("  Asset #{$asset->id} ({$hostname}, {$connection}): in LDAP gefunden. Last Logon: ".($asset->last_logon?->format('d.m.Y H:i') ?? '—').', Last Logon TS: '.($asset->last_logon_timestamp?->format('d.m.Y H:i') ?? '—'));
                }
            } else {
                if ($verbose) {
                    $this->line("  Asset #{$asset->id} ({$hostname}, {$connection}): nicht in LDAP gefunden – nur Last Checked gesetzt.");
                }
            }

            $asset->domain_last_checked = $now;
            $asset->save();
            $count++;
        }

        $this->info("  → {$count} Assets aktualisiert.");

        return self::SUCCESS;
    }
}
