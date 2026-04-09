<?php

namespace Hwkdo\IntranetAppAssets\Jobs;

use Hwkdo\IntranetAppAssets\Enums\D3InvoiceAnalysisStatus;
use Hwkdo\IntranetAppAssets\Models\D3InvoiceAnalysis;
use Hwkdo\IntranetAppAssets\Services\D3InvoiceVisionAnalysisService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyzeD3InvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $uniqueFor = 7200;

    public function __construct(
        public string $d3DocumentId,
        public bool $force = false,
    ) {
        $queue = config('intranet-app-assets.d3_invoice_analysis_queue');
        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }

        $httpTimeout = (int) config('intranet-app-assets.d3_invoice_vision_http_timeout', 1200);
        $this->timeout = max(300, $httpTimeout + 600);
    }

    public function uniqueId(): string
    {
        return D3InvoiceAnalysis::normalizeDocumentId($this->d3DocumentId);
    }

    public function handle(D3InvoiceVisionAnalysisService $service): void
    {
        $id = D3InvoiceAnalysis::normalizeDocumentId($this->d3DocumentId);

        $skip = DB::transaction(function () use ($id): bool {
            $row = D3InvoiceAnalysis::query()->where('d3_document_id', $id)->lockForUpdate()->first();
            if ($row === null) {
                D3InvoiceAnalysis::create([
                    'd3_document_id' => $id,
                    'status' => D3InvoiceAnalysisStatus::Pending,
                ]);

                return false;
            }

            if ($row->status === D3InvoiceAnalysisStatus::Completed && ! $this->force && $row->isDispatchRedundant()) {
                return true;
            }

            return false;
        });

        if ($skip) {
            return;
        }

        $row = D3InvoiceAnalysis::query()->where('d3_document_id', $id)->first();
        if ($row === null) {
            Log::warning('AnalyzeD3InvoiceJob: Zeile fehlt nach Transaktion.', ['d3_document_id' => $id]);

            return;
        }

        $visionModel = trim((string) config(
            'intranet-app-assets.d3_invoice_vision_model',
            config('openwebui-api-laravel.default_model', '')
        ));
        if ($visionModel === '') {
            $row->markFailed('Kein Vision-Modell konfiguriert. Setze intranet-app-assets.d3_invoice_vision_model oder OPENWEBUI_DEFAULT_MODEL.');

            return;
        }

        $visionToken = trim((string) config('openwebui-api-laravel.api_key', ''));
        if ($visionToken === '') {
            $row->markFailed('Für die Vision-Analyse wird ein OpenWebUI-API-Token benötigt (OPENWEBUI_API_KEY).');

            return;
        }

        try {
            $response = $service->analyze($id, $visionModel, $visionToken, false);
            $row->refresh();
            $row->markCompleted($service->payloadForStorage($response), $visionModel);
        } catch (\Throwable $e) {
            $row->refresh();
            $row->markFailed($e->getMessage());
            throw $e;
        }
    }
}
