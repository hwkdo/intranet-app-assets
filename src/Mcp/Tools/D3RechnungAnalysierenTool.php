<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Tools;

use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\IntranetAppAssets\Enums\D3InvoiceAnalysisStatus;
use Hwkdo\IntranetAppAssets\Jobs\AnalyzeD3InvoiceJob;
use Hwkdo\IntranetAppAssets\Models\D3InvoiceAnalysis;
use Hwkdo\IntranetAppAssets\Services\D3InvoiceVisionAnalysisService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsOpenWorld]
class D3RechnungAnalysierenTool extends Tool
{
    protected string $name = 'd3_rechnung_analysieren';

    protected string $description = 'Bevorzugtes Tool für Rechnungsauswertung, sobald die D3-Dokument-ID (T-Nummer) bekannt ist — typischerweise aus assets_suchen oder assets_abfragen als Feld invoice_number (T…). Nutzt gecachte Vision-Analyse wenn vorhanden (schnell); sonst Hintergrund-Job oder mit force_refresh Live-Analyse. Strukturiertes JSON: Lieferant, Summen, Positionen, Seriennummern, Warnungen. Nach Asset-Treffern mit passender invoice_number: dieses Tool als Nächstes, nicht d3_rechnung_suchen. Wenn invoice_number fehlt oder kein T-Format: zuerst d3_rechnung_suchen, dann dieses Tool mit der gewählten id.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $traceId = bin2hex(random_bytes(6));
        $startedAt = microtime(true);

        $validated = $request->validate([
            'id' => ['required', 'string', 'regex:/^T\d+$/'],
            'vision_model' => ['nullable', 'string', 'max:255'],
            'include_raw_ocr' => ['nullable', 'boolean'],
            'vision_token' => ['nullable', 'string', 'max:2000'],
            'force_refresh' => ['nullable', 'boolean'],
        ], [
            'id.regex' => 'Ungültiges Format für "id". Erwartet wird "T" gefolgt von Ziffern (z. B. T12345).',
        ]);

        $documentId = trim((string) $validated['id']);
        $includeRawOcr = (bool) ($validated['include_raw_ocr'] ?? false);
        $forceRefresh = (bool) ($validated['force_refresh'] ?? false);
        $visionModel = trim((string) ($validated['vision_model'] ?? ''));
        if ($visionModel === '') {
            $visionModel = (string) config('intranet-app-assets.d3_invoice_vision_model', config('openwebui-api-laravel.default_model', ''));
        }
        if ($visionModel === '') {
            return Response::error('Kein Vision-Modell konfiguriert. Setze intranet-app-assets.d3_invoice_vision_model oder OPENWEBUI_DEFAULT_MODEL.');
        }

        $visionToken = trim((string) ($validated['vision_token'] ?? config('openwebui-api-laravel.api_key', '')));
        if ($visionToken === '') {
            return Response::error('Für die Vision-Analyse wird ein OpenWebUI-API-Token benötigt (vision_token oder OPENWEBUI_API_KEY).');
        }

        Log::info('d3_rechnung_analysieren called', [
            'id' => $documentId,
            'vision_model' => $visionModel,
            'include_raw_ocr' => $includeRawOcr,
            'force_refresh' => $forceRefresh,
            'trace_id' => $traceId,
        ]);

        if (! class_exists(D3Client::class)) {
            return Response::error('D3-REST-Client ist nicht verfügbar.');
        }

        if (! $forceRefresh) {
            $cached = D3InvoiceAnalysis::findCompletedPayloadForDocument($documentId);
            if ($cached !== null) {
                $response = $this->applyAnalysisStatusAndSource($cached, 'completed', [
                    'method' => 'cached',
                    'model' => $visionModel,
                    'from_cache' => true,
                ]);
                unset($response['raw_ocr_markdown']);

                $this->logReady($traceId, $startedAt);

                return Response::structured($response);
            }

            $row = D3InvoiceAnalysis::query()->where('d3_document_id', D3InvoiceAnalysis::normalizeDocumentId($documentId))->first();
            if ($row?->status === D3InvoiceAnalysisStatus::Pending) {
                return Response::structured($this->pendingPayload($documentId, $visionModel, 'Analyse läuft im Hintergrund oder steht noch aus. Erneut versuchen, sobald sie abgeschlossen ist.'));
            }
            if ($row?->status === D3InvoiceAnalysisStatus::Failed) {
                D3InvoiceAnalysis::requestAnalysis($documentId, false);
                AnalyzeD3InvoiceJob::dispatch($documentId, false);

                return Response::structured($this->pendingPayload($documentId, $visionModel, 'Vorherige Analyse fehlgeschlagen; erneuter Versuch wurde eingereiht.'));
            }

            D3InvoiceAnalysis::requestAnalysis($documentId, false);
            AnalyzeD3InvoiceJob::dispatch($documentId, false);

            return Response::structured($this->pendingPayload($documentId, $visionModel, 'Analyse wurde in die Warteschlange gestellt. Bitte später erneut abfragen.'));
        }

        $service = app(D3InvoiceVisionAnalysisService::class);

        try {
            $response = $service->analyze($documentId, $visionModel, $visionToken, $includeRawOcr, $traceId);
            $row = D3InvoiceAnalysis::query()->firstOrCreate(
                ['d3_document_id' => D3InvoiceAnalysis::normalizeDocumentId($documentId)],
                ['status' => D3InvoiceAnalysisStatus::Pending],
            );
            $row->markCompleted($service->payloadForStorage($response), $visionModel);

            $response = $this->applyAnalysisStatusAndSource($response, 'completed', [
                'method' => 'vision_ocr',
                'model' => $visionModel,
                'from_cache' => false,
            ]);

            $this->logReady($traceId, $startedAt);

            return Response::structured($response);
        } catch (\Throwable $e) {
            Log::warning('d3_rechnung_analysieren failed', [
                'id' => $documentId,
                'message' => $e->getMessage(),
                'trace_id' => $traceId,
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            return Response::error('Die D3-Rechnungsanalyse ist fehlgeschlagen: '.$e->getMessage());
        }
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('D3-Dokument-ID (T-Nummer), z. B. T12345.')
                ->required(),
            'vision_model' => $schema->string()
                ->description('Optionales Vision-Modell; sonst intranet-app-assets.d3_invoice_vision_model bzw. OPENWEBUI_DEFAULT_MODEL.')
                ->nullable(),
            'include_raw_ocr' => $schema->boolean()
                ->description('Wenn true, wird zusätzlich der kombinierte OCR-Markdowntext zurückgegeben (nur bei Live-Analyse mit force_refresh).')
                ->nullable(),
            'vision_token' => $schema->string()
                ->description('Optionales OpenWebUI API Token. Wenn leer, wird OPENWEBUI_API_KEY verwendet.')
                ->nullable(),
            'force_refresh' => $schema->boolean()
                ->description('Wenn true: Cache ignorieren und sofortige Vision-Analyse (langsam). Standard false: Cache oder Warteschlange.')
                ->nullable(),
        ];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'analysis_status' => $schema->string()
                ->description('completed: vollständige Daten; pending: Analyse läuft oder wartet.')
                ->required(),
            'analysis_message' => $schema->string()
                ->description('Bei pending: kurzer Hinweis für den Nutzer.')
                ->nullable(),
            'document' => $schema->object([
                'id' => $schema->string()->required(),
                'display_name' => $schema->string()->nullable(),
                'category' => $schema->string()->nullable(),
                'rechnungsnummer' => $schema->string()->nullable(),
                'belegtyp' => $schema->string()->nullable(),
                'belegdatum' => $schema->string()->nullable(),
            ])->required(),
            'supplier' => $schema->object([
                'name' => $schema->string()->nullable(),
                'address' => $schema->string()->nullable(),
                'iban' => $schema->string()->nullable(),
                'vat_id' => $schema->string()->nullable(),
            ])->required(),
            'invoice' => $schema->object([
                'invoice_number' => $schema->string()->nullable(),
                'invoice_date' => $schema->string()->nullable(),
                'customer_number' => $schema->string()->nullable(),
                'purchase_order_reference' => $schema->string()->nullable(),
            ])->required(),
            'totals' => $schema->object([
                'currency' => $schema->string()->nullable(),
                'net' => $schema->number()->nullable(),
                'tax' => $schema->number()->nullable(),
                'gross' => $schema->number()->nullable(),
            ])->required(),
            'line_items' => $schema->array()
                ->items($schema->object([
                    'position' => $schema->string()->nullable(),
                    'description' => $schema->string()->nullable(),
                    'quantity' => $schema->number()->nullable(),
                    'unit' => $schema->string()->nullable(),
                    'unit_price' => $schema->number()->nullable(),
                    'total_price' => $schema->number()->nullable(),
                    'serial_numbers' => $schema->array()->items($schema->string())->required(),
                ]))
                ->required(),
            'serial_numbers' => $schema->array()
                ->items($schema->string())
                ->required(),
            'warnings' => $schema->array()
                ->items($schema->string())
                ->required(),
            'confidence' => $schema->number()
                ->nullable(),
            'source' => $schema->object([
                'method' => $schema->string()->required(),
                'model' => $schema->string()->required(),
                'processed_pages' => $schema->integer()->required(),
                'total_pages' => $schema->integer()->required(),
                'truncated' => $schema->boolean()->required(),
                'from_cache' => $schema->boolean()->required(),
            ])->required(),
            'raw_ocr_markdown' => $schema->string()->nullable(),
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $sourceOverrides  method, model, from_cache — pages/total/truncated aus base übernehmen
     * @return array<string, mixed>
     */
    private function applyAnalysisStatusAndSource(array $base, string $analysisStatus, array $sourceOverrides): array
    {
        $source = is_array($base['source'] ?? null) ? $base['source'] : [];
        $mergedSource = array_merge([
            'method' => 'vision_ocr',
            'model' => (string) ($source['model'] ?? ''),
            'processed_pages' => (int) ($source['processed_pages'] ?? 0),
            'total_pages' => (int) ($source['total_pages'] ?? 0),
            'truncated' => (bool) ($source['truncated'] ?? false),
            'from_cache' => false,
        ], $sourceOverrides);

        $base['source'] = $mergedSource;
        $base['analysis_status'] = $analysisStatus;
        $base['analysis_message'] = null;

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingPayload(string $documentId, string $visionModel, string $message): array
    {
        return [
            'analysis_status' => 'pending',
            'analysis_message' => $message,
            'document' => [
                'id' => $documentId,
                'display_name' => null,
                'category' => null,
                'rechnungsnummer' => null,
                'belegtyp' => null,
                'belegdatum' => null,
            ],
            'supplier' => [
                'name' => null,
                'address' => null,
                'iban' => null,
                'vat_id' => null,
            ],
            'invoice' => [
                'invoice_number' => null,
                'invoice_date' => null,
                'customer_number' => null,
                'purchase_order_reference' => null,
            ],
            'totals' => [
                'currency' => null,
                'net' => null,
                'tax' => null,
                'gross' => null,
            ],
            'line_items' => [],
            'serial_numbers' => [],
            'warnings' => [$message],
            'confidence' => null,
            'source' => [
                'method' => 'pending',
                'model' => $visionModel,
                'processed_pages' => 0,
                'total_pages' => 0,
                'truncated' => false,
                'from_cache' => false,
            ],
        ];
    }

    private function logReady(string $traceId, float $startedAt): void
    {
        if (! (bool) config('intranet-app-assets.d3_invoice_ocr_debug_log', true)) {
            return;
        }

        Log::info('d3_rechnung_analysieren.progress', [
            'trace_id' => $traceId,
            'stage' => 'response.ready',
            'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
