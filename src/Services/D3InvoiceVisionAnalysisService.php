<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\IntranetAppAssets\Contracts\D3InvoiceVisionLlmClientInterface;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf as PdfToImagePdf;

class D3InvoiceVisionAnalysisService
{
    public function __construct(
        private D3InvoiceVisionLlmClientFactory $llmClientFactory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(
        string $documentId,
        string $visionModel,
        ?string $visionToken = null,
        bool $includeRawOcr = false,
        ?string $traceId = null,
    ): array {
        $traceId ??= bin2hex(random_bytes(6));
        $documentId = trim($documentId);
        $llm = $this->llmClientFactory->make();
        $tokenOverride = $this->normalizeBearerOverride($visionToken);

        if (! class_exists(D3Client::class)) {
            throw new \RuntimeException('D3-REST-Client ist nicht verfügbar.');
        }

        $pdfFile = null;
        $rasterDir = null;

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

            $this->logProgress($traceId, 'pdf_raster.start');
            [$imagePaths, $rasterDir, $totalPages, $truncated] = $this->rasterPdfToImagePaths($pdfFile);
            $this->logProgress($traceId, 'pdf_raster.done', [
                'raster_dir' => $rasterDir,
                'total_pages' => $totalPages,
                'processed_pages' => count($imagePaths),
                'truncated' => $truncated,
            ]);

            $rawPerPage = $this->extractPerPageOcrMarkdown($imagePaths, $visionModel, $llm, $tokenOverride, $traceId);
            $this->logProgress($traceId, 'vision_ocr_pages.done', [
                'chunks' => count($rawPerPage),
            ]);
            if ($rawPerPage === []) {
                throw new \RuntimeException('Aus dem Dokument konnte kein OCR-Text erzeugt werden.');
            }

            $combinedOcr = $this->combinePageMarkdown($rawPerPage);
            $this->logProgress($traceId, 'vision_structured_analysis.start', ['ocr_chars' => mb_strlen($combinedOcr)]);
            $analysisRaw = $this->analyzeCombinedOcr($combinedOcr, $visionModel, $llm, $tokenOverride);
            $this->logProgress($traceId, 'vision_structured_analysis.done', ['analysis_keys' => array_keys($analysisRaw)]);
            $analysis = $this->normalizeAnalysisData($analysisRaw);
            $this->logProgress($traceId, 'normalize_analysis.done', [
                'line_items' => count($analysis['line_items']),
                'serial_numbers' => count($analysis['serial_numbers']),
                'warnings' => count($analysis['warnings']),
            ]);

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
                    'method' => 'vision_ocr',
                    'model' => $visionModel,
                    'processed_pages' => count($rawPerPage),
                    'total_pages' => $totalPages,
                    'truncated' => $truncated,
                    'from_cache' => false,
                ],
            ];

            if ($includeRawOcr) {
                $response['raw_ocr_markdown'] = $combinedOcr;
            }

            return $response;
        } finally {
            if ($pdfFile !== null && is_file($pdfFile)) {
                @unlink($pdfFile);
            }
            if ($rasterDir !== null) {
                $this->deleteDirectoryRecursive($rasterDir);
            }
        }
    }

    /**
     * Strips volatile fields for persistence (no raw OCR).
     *
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

    /**
     * @return array{0: list<string>, 1: string, 2: int, 3: bool}
     */
    private function rasterPdfToImagePaths(string $pdfPath): array
    {
        $maxPages = max(1, min(50, (int) config('intranet-app-assets.d3_invoice_ocr_max_pages', 12)));
        $dpi = max(120, min(300, (int) config('intranet-app-assets.d3_invoice_ocr_dpi', 180)));

        $rasterDir = storage_path('app/private/d3-invoice-ocr/'.bin2hex(random_bytes(8)));
        if (! @mkdir($rasterDir, 0755, true) && ! is_dir($rasterDir)) {
            throw new \RuntimeException('Temporäres Verzeichnis für PDF-Rasterung konnte nicht angelegt werden.');
        }

        $pdf = new PdfToImagePdf($pdfPath);
        $pdf->format(OutputFormat::Png)->resolution($dpi);

        $totalPages = $pdf->pageCount();
        if ($totalPages < 1) {
            throw new \RuntimeException('PDF enthält keine Seiten.');
        }

        $usePages = min($totalPages, $maxPages);
        $truncated = $totalPages > $maxPages;
        $pdf->selectPages(...range(1, $usePages));

        $saved = $pdf->save($rasterDir, 'page-');
        if ($saved === []) {
            throw new \RuntimeException('PDF konnte nicht in Bilder umgewandelt werden (Imagick/Ghostscript?).');
        }

        sort($saved);

        return [$saved, $rasterDir, $totalPages, $truncated];
    }

    /**
     * @return array{request: int, connect: int}
     */
    private function d3VisionHttpTimeouts(): array
    {
        return [
            'request' => max(1, (int) config('intranet-app-assets.d3_invoice_vision_http_timeout', 1200)),
            'connect' => max(1, (int) config('intranet-app-assets.d3_invoice_vision_connect_timeout', 30)),
        ];
    }

    private function normalizeBearerOverride(?string $visionToken): ?string
    {
        $t = trim((string) ($visionToken ?? ''));

        return $t === '' ? null : $t;
    }

    /**
     * @param  list<string>  $imagePaths
     * @return list<string>
     */
    private function extractPerPageOcrMarkdown(
        array $imagePaths,
        string $model,
        D3InvoiceVisionLlmClientInterface $llm,
        ?string $tokenOverride,
        string $traceId,
    ): array {
        $chunks = [];
        $httpTimeouts = $this->d3VisionHttpTimeouts();

        foreach ($imagePaths as $index => $imgPath) {
            if (! is_file($imgPath)) {
                continue;
            }

            $page = $index + 1;
            $pageStartedAt = microtime(true);
            $this->logProgress($traceId, 'vision_ocr_page.start', [
                'page' => $page,
                'image_path' => $imgPath,
                'image_size_bytes' => @filesize($imgPath) ?: null,
                'http_timeout_seconds' => $httpTimeouts['request'],
                'connect_timeout_seconds' => $httpTimeouts['connect'],
            ]);
            $prompt = <<<PROMPT
Du siehst eine gescannte Rechnungsseite.
Extrahiere den gesamten lesbaren Text so originalgetreu wie möglich in Markdown.

Regeln:
- Keine inhaltliche Interpretation.
- Lesereihenfolge von oben nach unten.
- Unleserliches als [unleserlich] markieren.
- Gib nur den extrahierten Text aus.
PROMPT;

            try {
                $response = $llm->chatCompletionWithImageFile(
                    $model,
                    $prompt,
                    $imgPath,
                    $httpTimeouts['request'],
                    $httpTimeouts['connect'],
                    $tokenOverride,
                );
            } catch (\Throwable $e) {
                $this->logProgress($traceId, 'vision_ocr_page.failed', [
                    'page' => $page,
                    'message' => $e->getMessage(),
                    'elapsed_ms' => (int) ((microtime(true) - $pageStartedAt) * 1000),
                ]);
                throw $e;
            }
            $text = $this->normalizeAssistantContent(data_get($response, 'choices.0.message.content'));
            if ($text !== '') {
                $chunks[] = "## Seite {$page}\n\n".$text;
            }
            $this->logProgress($traceId, 'vision_ocr_page.done', [
                'page' => $page,
                'text_chars' => mb_strlen($text),
                'elapsed_ms' => (int) ((microtime(true) - $pageStartedAt) * 1000),
            ]);
        }

        return $chunks;
    }

    /**
     * @param  list<string>  $chunks
     */
    private function combinePageMarkdown(array $chunks): string
    {
        return trim(implode("\n\n---\n\n", $chunks));
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeCombinedOcr(
        string $ocrText,
        string $model,
        D3InvoiceVisionLlmClientInterface $llm,
        ?string $tokenOverride,
    ): array {
        $analysisPrompt = <<<PROMPT
Analysiere den folgenden OCR-Text einer Rechnung.
Antworte ausschließlich als valides JSON-Objekt ohne Markdown-Fences.

Nutze exakt diese Struktur:
{
  "supplier": {"name": null, "address": null, "iban": null, "vat_id": null},
  "invoice": {"invoice_number": null, "invoice_date": null, "customer_number": null, "purchase_order_reference": null},
  "totals": {"currency": null, "net": null, "tax": null, "gross": null},
  "line_items": [
    {"position": null, "description": null, "quantity": null, "unit": null, "unit_price": null, "total_price": null, "serial_numbers": []}
  ],
  "serial_numbers": [],
  "warnings": [],
  "confidence": null
}

Regeln:
- Zahlen als JSON number (Punkt als Dezimaltrennzeichen), sonst null.
- Fehlende/unklare Felder als null.
- serial_numbers deduplizieren.
- warnings für Unsicherheiten ergänzen.

OCR-Text:
{$ocrText}
PROMPT;

        $httpTimeouts = $this->d3VisionHttpTimeouts();
        $response = $llm->chatCompletionWithMessages(
            $model,
            [
                [
                    'role' => 'user',
                    'content' => $analysisPrompt,
                ],
            ],
            $httpTimeouts['request'],
            $httpTimeouts['connect'],
            $tokenOverride,
        );

        $content = $this->normalizeAssistantContent(data_get($response, 'choices.0.message.content'));
        $json = $this->extractJsonFromContent($content);
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Vision-Analyse lieferte kein valides JSON.');
        }

        return $decoded;
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

    private function normalizeAssistantContent(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $part) {
            if (is_string($part)) {
                $parts[] = $part;
                continue;
            }

            if (is_array($part) && ($part['type'] ?? '') === 'text') {
                $parts[] = (string) ($part['text'] ?? '');
            }
        }

        return trim(implode("\n", $parts));
    }

    private function deleteDirectoryRecursive(string $dir): void
    {
        if ($dir === '' || ! is_dir($dir)) {
            return;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
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
