<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Contracts\LdapComputerServiceInterface;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SetItexiaIdsCommand extends Command
{
    protected $signature = 'intranet-app-assets:set-itexia-ids
                            {--details : Pro Asset ausgeben, ob gesetzt}';

    protected $description = 'Setzt die Itexia-IDs für alle Domain-Assets mit Itexia-ID und Domain-Connection in Active Directory.';

    public function handle(): int
    {
        if (! $this->laravel->bound(LdapComputerServiceInterface::class)) {
            $this->warn('LDAP-Computer-Service nicht registriert. Binde '.LdapComputerServiceInterface::class.' in der App (z. B. AppServiceProvider).');

            return self::FAILURE;
        }

        $domainTypeIds = AssetType::where('is_domain_object', true)->pluck('id');
        $assets = Asset::whereIn('asset_type_id', $domainTypeIds)
            ->whereNotNull('domain_connection')
            ->whereNotNull('itexia_id')
            ->where(fn ($q) => $q->where('itexia_id', '!=', ''))
            ->whereNotNull('name')
            ->where(fn ($q) => $q->where('name', '!=', ''))
            ->get();

        $this->info('Setze Itexia-IDs für '.$assets->count().' Assets…');

        $ldap = $this->laravel->make(LdapComputerServiceInterface::class);
        $verbose = $this->option('details');
        $count = 0;

        foreach ($assets as $asset) {
            try {
                $ok = $ldap->setItexiaId($asset->name, $asset->itexia_id, $asset->domain_connection ?? 'default');
                if ($ok) {
                    $count++;
                    if ($verbose) {
                        $this->line("  [OK] {$asset->name} ({$asset->domain_connection}) → {$asset->itexia_id}");
                    }
                } else {
                    if ($verbose) {
                        $this->line("  [--] {$asset->name}: Computer in AD nicht gefunden.");
                    }
                }
            } catch (\Throwable $e) {
                Log::error('SetItexiaIdsCommand: '.$e->getMessage(), ['asset_id' => $asset->id]);
                if ($verbose) {
                    $this->line("  [!!] {$asset->name}: {$e->getMessage()}");
                }
            }
        }

        $this->info("  → {$count} Itexia-IDs in AD gesetzt.");

        return self::SUCCESS;
    }
}
