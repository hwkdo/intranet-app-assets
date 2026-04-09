<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Enums\D3InvoiceAnalysisStatus;
use Hwkdo\IntranetAppAssets\Jobs\AnalyzeD3InvoiceJob;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\D3InvoiceAnalysis;
use Hwkdo\IntranetAppAssets\Services\D3InvoiceValidationService;
use Illuminate\Console\Command;
class D3InvoiceAnalysesBackfillCommand extends Command
{
    protected $signature = 'intranet-app-assets:d3-invoice-analyses-backfill
                            {--dry-run : Nur zählen, keine Jobs}
                            {--retry-failed : Nur fehlende oder fehlgeschlagene T-Nummern}
                            {--force : Auch bei completed erneut analysieren}
                            {--delay=0 : Zusätzliche Verzögerung in Sekunden zwischen Dispatch-Stufen (pro 50 IDs)}';

    protected $description = 'Reiht D3-Rechnungsanalysen (Vision-Cache) für distinct invoice_number (T…) aus Assets in die Queue ein.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $retryFailed = (bool) $this->option('retry-failed');
        $force = (bool) $this->option('force');
        $delayChunkSeconds = max(0, (int) $this->option('delay'));

        $documentIds = $this->collectDistinctDocumentIds();

        if ($retryFailed && ! $force) {
            $documentIds = $documentIds->filter(function (string $id): bool {
                $row = D3InvoiceAnalysis::query()->where('d3_document_id', D3InvoiceAnalysis::normalizeDocumentId($id))->first();
                if ($row === null) {
                    return true;
                }
                if ($row->status === D3InvoiceAnalysisStatus::Failed) {
                    return true;
                }

                return false;
            });
        } elseif (! $force) {
            $documentIds = $documentIds->filter(function (string $id): bool {
                $row = D3InvoiceAnalysis::query()->where('d3_document_id', D3InvoiceAnalysis::normalizeDocumentId($id))->first();
                if ($row === null) {
                    return true;
                }
                if ($row->status === D3InvoiceAnalysisStatus::Failed) {
                    return true;
                }
                if ($row->status === D3InvoiceAnalysisStatus::Completed && ! $row->isDispatchRedundant()) {
                    return true;
                }

                return false;
            });
        }

        $ids = $documentIds->values()->all();
        $this->info('Kandidaten: '.count($ids).' distinct T-Nummern.');

        if ($dryRun) {
            foreach ($ids as $id) {
                $this->line("  [dry-run] {$id}");
            }

            return self::SUCCESS;
        }

        $dispatched = 0;
        foreach (array_chunk($ids, 50) as $index => $chunk) {
            if ($index > 0 && $delayChunkSeconds > 0) {
                sleep($delayChunkSeconds);
            }
            foreach ($chunk as $id) {
                D3InvoiceAnalysis::requestAnalysis($id, $force);
                AnalyzeD3InvoiceJob::dispatch($id, $force);
                $dispatched++;
            }
        }

        $this->info("Fertig: {$dispatched} Jobs dispatched.");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function collectDistinctDocumentIds(): \Illuminate\Support\Collection
    {
        return Asset::query()
            ->whereNotNull('invoice_number')
            ->where('invoice_number', 'like', 'T%')
            ->pluck('invoice_number')
            ->map(fn (mixed $v): string => trim((string) $v))
            ->unique()
            ->sort()
            ->values()
            ->filter(fn (string $id): bool => D3InvoiceValidationService::isValidFormat($id));
    }
}
