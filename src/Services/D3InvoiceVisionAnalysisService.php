<?php

namespace Hwkdo\IntranetAppAssets\Services;

use App\Services\PdfService;
use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\IntranetAppAssets\Ai\Agents\D3InvoiceAnalysisAgent;
use Hwkdo\IntranetAppBase\Contracts\AiConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use Hwkdo\IntranetAppBase\Data\AiRequestContext;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Illuminate\Support\Facades\Log;

class D3InvoiceVisionAnalysisService
{
    public function __construct(
        private PdfService $pdfService,
        private IntranetAiGatewayInterface $gateway,
        private AiConfigResolverInterface $aiConfig,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(
        string $documentId,
        bool $includeRawOcr = false,
        ?string $traceId = null,
    ): array {
        $traceId ??= bin2hex(random_bytes(6));
        $documentId = trim($documentId);

        if (! class_exists(D3Client::class)) {
            throw new \RuntimeException('D3-REST-Client ist nicht verfügbar.');
        }

        $pdfFile = null;

        try {
            $this->logProgress($traceId, 'resolve_d3_client.start');
            $client = app(D3Client::class);
            $this->logProgress($traceId, 'resolve_d3_client.done');

            $this->logProgress($traceId, 'd3_get_doc.start', ['document_id' => $documentId]);
            $document = $client->getDoc($documentId, true);
            $this->logProgress($traceId, 'd3_get_doc.done', ['has_document' => is_array($document), 'document_caption' => $document['caption'] ?? null]);
            if (! is_array($document) || $document === []) {
                throw new \RuntimeException('Das angeforderte D3-Dokument konnte nicht geladen werden.');
            }

            $this->logProgress($traceId, 'd3_download_pdf.start');
            $pdfFile = $this->downloadD3PdfToTempFile($client, $documentId);
            $this->logProgress($traceId, 'd3_download_pdf.done', ['pdf_file' => $pdfFile, 'pdf_size_bytes' => @filesize($pdfFile) ?: null]);

            $embeddedText = $this->embeddedText($pdfFile);
            $usesEmbeddedText = $this->hasUsableText($embeddedText);
            $text = $usesEmbeddedText
                ? $embeddedText
                : trim($this->gateway->parseAppDocument($pdfFile, 'assets'));

            if (! $this->hasUsableText($text)) {
                throw new \RuntimeException('Aus dem Dokument konnte kein Text gelesen werden.');
            }

            $this->logProgress($traceId, 'structured_analysis.start', [
                'text_source' => $usesEmbeddedText ? 'embedded_text' : 'document_parse',
                'text_chars' => mb_strlen($text),
            ]);

            $structured = $this->gateway->agent(
                new D3InvoiceAnalysisAgent,
                "Rechnungstext:\n\n".$text,
                new AiRequestContext(
                    appIdentifier: 'assets',
                    capability: AiCapability::Agent,
                ),
            );

            if (! is_array($structured)) {
                throw new \RuntimeException('KI-Antwort enthält keine strukturierten Rechnungsdaten.');
            }

            $analysis = $this->normalizeAnalysisData($structured);
            $config = $this->aiConfig->resolve('assets', AiCapability::Agent);
            $model = trim((string) ($config->model ?? ''));
            if ($model === '') {
                $model = $config->provider->value;
            }

            $response = [
                'document' => [
                    'id' => $documentId,
                    'display_name' => $document['caption'] ?? null,
                    'category' => $document['category']['name'] ?? null,
                    'rechnungsnummer' => $this->getDisplayProperty($document, '60') ?? null,
                    'belegtyp' => $this->getDisplayProperty($document, '82') ?? null,
                    'belegdatum' => $this->getDisplayProperty($document, '8') ?? null,
                ],
                'supplier' => $analysis['supplier'],
                'invoice' => $analysis['invoice'],
                'totals' => $analysis['totals'],
                'line_items' => $analysis['line_items'],
                'serial_numbers' => $analysis['serial_numbers'],
                'warnings' => $analysis['warnings'],
                'confidence' => $analysis['confidence'],
                'source' => [
                    'method' => $usesEmbeddedText ? 'pdf_text' : 'document_parse',
                    'model' => $model,
                    'processed_pages' => 0,
                    'total_pages' => 0,
                    'truncated' => false,
                    'from_cache' => false,
                ],
            ];

            if ($includeRawOcr) {
                $response['raw_ocr_markdown'] = $text;
            }

            return $response;
        } finally {
            if ($pdfFile !== null && is_file($pdfFile)) {
                @unlink($pdfFile);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function payloadForStorage(array $response): array
    {
        unset($response['raw_ocr_markdown']);

        return $response;
    }

    public function extractJsonFromContent(string $content): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            throw new \RuntimeException('Vision-Analyse lieferte leeren Inhalt.');
        }

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace === false || $lastBrace === false || $lastBrace < $firstBrace) {
            throw new \RuntimeException('Kein JSON-Objekt im Vision-Output gefunden.');
        }

        return substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{
     *   supplier: array{name: ?string, address: ?string, iban: ?string, vat_id: ?string},
     *   invoice: array{invoice_number: ?string, invoice_date: ?string, customer_number: ?string, purchase_order_reference: ?string},
     *   totals: array{currency: ?string, net: ?float, tax: ?float, gross: ?float},
     *   line_items: list<array{position: ?string, description: ?string, quantity: ?float, unit: ?string, unit_price: ?float, total_price: ?float, serial_numbers: list<string>}>,
     *   serial_numbers: list<string>,
     *   warnings: list<string>,
     *   confidence: ?float
     * }
     */
    public function normalizeAnalysisData(array $raw): array
    {
        $supplier = is_array($raw['supplier'] ?? null) ? $raw['supplier'] : [];
        $invoice = is_array($raw['invoice'] ?? null) ? $raw['invoice'] : [];
        $totals = is_array($raw['totals'] ?? null) ? $raw['totals'] : [];
        $lineItemsRaw = is_array($raw['line_items'] ?? null) ? $raw['line_items'] : [];
        $warningsRaw = is_array($raw['warnings'] ?? null) ? $raw['warnings'] : [];

        $lineItems = [];
        $lineSerials = [];
        foreach ($lineItemsRaw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemSerials = $this->normalizeStringList($item['serial_numbers'] ?? []);
            $lineSerials = array_merge($lineSerials, $itemSerials);

            $lineItems[] = [
                'position' => $this->nullableString($item['position'] ?? null),
                'description' => $this->nullableString($item['description'] ?? null),
                'quantity' => $this->nullableFloat($item['quantity'] ?? null),
                'unit' => $this->nullableString($item['unit'] ?? null),
                'unit_price' => $this->nullableFloat($item['unit_price'] ?? null),
                'total_price' => $this->nullableFloat($item['total_price'] ?? null),
                'serial_numbers' => $itemSerials,
            ];
        }

        $globalSerials = $this->normalizeStringList($raw['serial_numbers'] ?? []);
        $serialNumbers = array_values(array_unique(array_merge($globalSerials, $lineSerials)));

        return [
            'supplier' => [
                'name' => $this->nullableString($supplier['name'] ?? null),
                'address' => $this->nullableString($supplier['address'] ?? null),
                'iban' => $this->nullableString($supplier['iban'] ?? null),
                'vat_id' => $this->nullableString($supplier['vat_id'] ?? null),
            ],
            'invoice' => [
                'invoice_number' => $this->nullableString($invoice['invoice_number'] ?? null),
                'invoice_date' => $this->nullableString($invoice['invoice_date'] ?? null),
                'customer_number' => $this->nullableString($invoice['customer_number'] ?? null),
                'purchase_order_reference' => $this->nullableString($invoice['purchase_order_reference'] ?? null),
            ],
            'totals' => [
                'currency' => $this->nullableString($totals['currency'] ?? null),
                'net' => $this->nullableFloat($totals['net'] ?? null),
                'tax' => $this->nullableFloat($totals['tax'] ?? null),
                'gross' => $this->nullableFloat($totals['gross'] ?? null),
            ],
            'line_items' => $lineItems,
            'serial_numbers' => $serialNumbers,
            'warnings' => $this->normalizeStringList($warningsRaw),
            'confidence' => $this->nullableFloat($raw['confidence'] ?? null),
        ];
    }

    private function downloadD3PdfToTempFile(D3Client $client, string $documentId): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'd3-invoice-');
        if ($tempFile === false) {
            throw new \RuntimeException('Temporäre PDF-Datei konnte nicht erstellt werden.');
        }

        $pdfPath = $tempFile.'.pdf';
        @rename($tempFile, $pdfPath);

        $downloadOk = $client->downloadDoc($documentId, $pdfPath);
        if (! $downloadOk || ! is_file($pdfPath) || filesize($pdfPath) === 0) {
            @unlink($pdfPath);
            throw new \RuntimeException('PDF-Download aus D3 fehlgeschlagen.');
        }

        return $pdfPath;
    }

    private function embeddedText(string $absolutePdfPath): string
    {
        try {
            return trim((string) $this->pdfService->pdfToText($absolutePdfPath));
        } catch (\Throwable) {
            return '';
        }
    }

    private function hasUsableText(string $text): bool
    {
        $normalized = trim(str_replace("\f", '', $text));

        return mb_strlen($normalized) >= 8;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = str_replace([' ', "\u{00A0}"], '', trim($value));
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $entry): bool => is_string($entry))
            ->map(fn (string $entry): string => trim($entry))
            ->filter(fn (string $entry): bool => $entry !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function getDisplayProperty(array $data, string $propertyId): ?string
    {
        $props = isset($data['displayProperties'])
            ? collect($data['displayProperties'])
            : collect($data['objectProperties'] ?? []);
        $found = $props->where('id', $propertyId)->first();

        if ($found !== null) {
            return $found['value'] ?? null;
        }

        $system = collect($data['systemProperties'] ?? []);
        $sysFound = $system->where('id', $propertyId)->first();

        return $sysFound['value'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logProgress(string $traceId, string $stage, array $context = []): void
    {
        if (! (bool) config('intranet-app-assets.d3_invoice_ocr_debug_log', true)) {
            return;
        }

        Log::info('d3_rechnung_analysieren.progress', array_merge([
            'trace_id' => $traceId,
            'stage' => $stage,
        ], $context));
    }
}
