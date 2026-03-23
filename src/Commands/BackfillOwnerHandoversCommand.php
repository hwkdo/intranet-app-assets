<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Illuminate\Console\Command;

class BackfillOwnerHandoversCommand extends Command
{
    protected $signature = 'intranet-app-assets:backfill-owner-handovers
                            {--dry-run : Nur anzeigen, was ergänzt würde}
                            {--limit=0 : Maximal N Assets verarbeiten (0 = unbegrenzt)}
                            {--user-id= : Optional nur Assets für diese user_id}';

    protected $description = 'Ergänzt fehlende Übergaben für Assets mit Besitzer (user_id).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $onlyUserId = $this->option('user-id');
        $onlyUserId = $onlyUserId !== null && $onlyUserId !== '' ? (int) $onlyUserId : null;

        $query = Asset::query()
            ->whereNotNull('user_id')
            ->select(['id', 'user_id']);

        if ($onlyUserId !== null) {
            $query->where('user_id', $onlyUserId);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $assets = $query->get();

        $this->info('Prüfe Assets mit Besitzer auf fehlende Übergaben…');
        if ($dryRun) {
            $this->warn('DRY-RUN: Es werden keine Änderungen geschrieben.');
        }

        $checked = 0;
        $created = 0;
        $alreadyOk = 0;

        foreach ($assets as $asset) {
            $checked++;
            $ownerUserId = $asset->user_id !== null ? (int) $asset->user_id : null;
            if ($ownerUserId === null) {
                continue;
            }

            $exists = Handover::query()
                ->where('asset_id', $asset->id)
                ->where('recipient_user_id', $ownerUserId)
                ->exists();

            if ($exists) {
                $alreadyOk++;

                continue;
            }

            $created++;
            if (! $dryRun) {
                Handover::create([
                    'asset_id' => $asset->id,
                    'recipient_user_id' => $ownerUserId,
                    'issuer_user_id' => null,
                    'confirmed_at' => null,
                    'confirmation_method' => null,
                ]);
            }
        }

        $this->line("  → geprüft: {$checked}");
        $this->line("  → bereits konsistent: {$alreadyOk}");
        $this->line('  → fehlende Übergaben '.($dryRun ? 'gefunden' : 'erstellt').": {$created}");

        return self::SUCCESS;
    }
}
