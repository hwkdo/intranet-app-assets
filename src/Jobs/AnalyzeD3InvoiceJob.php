<?php

namespace Hwkdo\IntranetAppAssets\Jobs;

use Hwkdo\IntranetAppAssets\Enums\D3InvoiceAnalysisStatus;
use Hwkdo\IntranetAppAssets\Enums\D3InvoiceVisionLlmProvider;
use Hwkdo\IntranetAppAssets\Models\D3InvoiceAnalysis;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Services\D3InvoiceVisionAnalysisService;
use Hwkdo\IntranetAppAssets\Support\D3InvoiceVisionModelResolver;
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

        $appSettings = IntranetAppAssetsSettings::resolvedAppSettings();
        $provider = $appSettings->d3InvoiceVisionLlmProvider;

        $visionModel = D3InvoiceVisionModelResolver::resolve($provider, null, $appSettings);
        if ($visionModel === '') {
            $row->markFailed(
                $provider === D3InvoiceVisionLlmProvider::Langdock
                    ? 'Kein Vision-Modell für Langdock. In den Assets-Admin-Einstellungen „Vision-Modell … Langdock“ setzen oder INTRANET_APP_ASSETS_D3_INVOICE_VISION_MODEL_LANGDOCK.'
                    : 'Kein Vision-Modell für Open Web UI. In den Assets-Admin-Einstellungen „Vision-Modell … Open Web UI“ setzen oder INTRANET_APP_ASSETS_D3_INVOICE_VISION_MODEL / OPENWEBUI_DEFAULT_MODEL.'
            );

            return;
        }

        $visionToken = match ($provider) {
            D3InvoiceVisionLlmProvider::Langdock => trim((string) config('services.langdock.api_key', '')),
            D3InvoiceVisionLlmProvider::OpenWebUi => trim((string) config('openwebui-api-laravel.api_key', '')),
        };
        if ($visionToken === '') {
            $row->markFailed(
                $provider === D3InvoiceVisionLlmProvider::Langdock
                    ? 'Für die Vision-Analyse wird ein Langdock-API-Key benötigt (LANGDOCK_API_KEY / services.langdock.api_key).'
                    : 'Für die Vision-Analyse wird ein OpenWebUI-API-Token benötigt (OPENWEBUI_API_KEY).'
            );

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
