<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Illuminate\Console\Command;

class SetDomainConnectionCommand extends Command
{
    protected $signature = 'intranet-app-assets:set-domain-connection';

    protected $description = 'Setzt die Domain Connection für alle Assets mit Domain-Typ, die noch keine haben (HWKDO → Verwaltung, sonst Schulung).';

    public function handle(): int
    {
        $domainTypeIds = AssetType::where('is_domain_object', true)->pluck('id');
        $assets = Asset::whereIn('asset_type_id', $domainTypeIds)
            ->whereNull('domain_connection')
            ->get();

        $this->info('Gefunden: '.$assets->count().' Assets ohne Domain Connection.');

        $count = 0;
        foreach ($assets as $asset) {
            $asset->domain_connection = str($asset->name ?? '')->startsWith('HWKDO') ? 'default' : 'schulung';
            $asset->save();
            $count++;
        }

        $this->info("  → {$count} Domain Connections gesetzt.");

        return self::SUCCESS;
    }
}
