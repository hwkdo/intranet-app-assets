<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Services\InvoiceAutoResolveService;
use Illuminate\Console\Command;

class InvoiceAutoResolveCommand extends Command
{
    protected $signature = 'intranet-app-assets:invoice-auto-resolve
                            {--details : Pro Asset eine Zeile ausgeben}
                            {--ignore-days-limit : Tage-Frist aus den App-Settings ignorieren: D3-Suche für alle Kandidaten (BEN ohne Rechnung), ohne Frist-Markierung}';

    protected $description = 'Sucht für Assets mit BEN und ohne Rechnungsnr. per D3 nach Zahlungsbelegen; nach Ablauf der konfigurierten Tage Markierung „fehlende Rechnungsnr.“ (Scheduler). Mit --ignore-days-limit: alle Kandidaten durchsuchen.';

    public function handle(InvoiceAutoResolveService $service): int
    {
        $settings = IntranetAppAssetsSettings::current()?->settings;
        $maxDays = $settings instanceof AppSettings
            ? $settings->invoiceAutoResolveMaxDays
            : (new AppSettings)->invoiceAutoResolveMaxDays;

        $verbose = (bool) $this->option('details');
        $ignoreDaysLimit = (bool) $this->option('ignore-days-limit');

        $processed = 0;
        $resolved = 0;
        $exhausted = 0;

        $modeHint = $ignoreDaysLimit ? ' (Tage-Frist ignoriert)' : '';
        $this->info('Starte Rechnungs-Auflösung'.$modeHint.'…');

        Asset::query()
            ->eligibleForInvoiceAutoResolve()
            ->orderBy('id')
            ->chunkById(100, function ($assets) use ($service, $maxDays, $ignoreDaysLimit, $verbose, &$processed, &$resolved, &$exhausted): void {
            foreach ($assets as $asset) {
                $beforeInvoice = $asset->invoice_number;
                $beforePending = (bool) $asset->invoice_number_pending;

                $service->processAsset($asset, $maxDays, $ignoreDaysLimit);

                $asset->refresh();
                $processed++;

                if ($asset->invoice_number !== $beforeInvoice && filled($asset->invoice_number)) {
                    $resolved++;
                    if ($verbose) {
                        $this->line("  Asset #{$asset->id}: Rechnungsnr. gesetzt ({$asset->invoice_number}).");
                    }
                } elseif (! $beforePending && $asset->invoice_number_pending) {
                    $exhausted++;
                    if ($verbose) {
                        $this->line("  Asset #{$asset->id}: Frist abgelaufen – als fehlende Rechnungsnr. markiert.");
                    }
                } elseif ($verbose) {
                    $this->line("  Asset #{$asset->id}: keine Änderung.");
                }
            }
        });

        $this->info("Fertig: {$processed} Assets verarbeitet, {$resolved} Rechnungsnr. gesetzt, {$exhausted} nach Frist markiert.");

        return self::SUCCESS;
    }
}
