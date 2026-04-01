<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Tools;

use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\IntranetAppAssets\Services\D3InvoiceValidationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld]
class D3RechnungAbrufenTool extends Tool
{
    protected string $name = 'd3_rechnung_abrufen';

    protected string $description = 'Ruft ein D3-Rechnungsdokument per T-Nummer ab und liefert Metadaten; optional kann das PDF als Base64 für KI-Analyse zurückgegeben werden.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $id = trim((string) $request->get('id', ''));
        $pdfHerunterladen = (bool) $request->get('pdf_herunterladen', false);

        if ($id === '') {
            return Response::error('Das Feld "id" ist erforderlich.');
        }

        if (! D3InvoiceValidationService::isValidFormat($id)) {
            return Response::error('Ungültiges Format für "id". Erwartet wird "T" gefolgt von Ziffern (z. B. T12345).');
        }

        if (! class_exists(D3Client::class)) {
            return Response::error('D3-REST-Client ist nicht verfügbar.');
        }

        Log::info('d3_rechnung_abrufen called', ['id' => $id, 'pdf_herunterladen' => $pdfHerunterladen]);

        try {
            $client = app(D3Client::class);
            $document = $client->getDoc($id, true);

            if (! is_array($document) || $document === []) {
                return Response::error('Das angeforderte D3-Dokument konnte nicht geladen werden.');
            }

            $response = [
                'id' => $id,
                'display_name' => $document['caption'] ?? null,
                'category' => $document['category']['name'] ?? null,
                'rechnungsnummer' => $this->getDisplayProperty($document, '60') ?? $id,
                'belegtyp' => $this->getDisplayProperty($document, '82'),
                'belegdatum' => $this->getDisplayProperty($document, '8'),
                'metadata' => [
                    'display_properties' => $document['displayProperties'] ?? [],
                    'system_properties' => $document['systemProperties'] ?? [],
                    'object_properties' => $document['objectProperties'] ?? [],
                ],
                'pdf' => null,
            ];

            if ($pdfHerunterladen) {
                $response['pdf'] = $this->downloadPdfAsBase64($client, $id);
            }

            return Response::structured($response);
        } catch (\Throwable $e) {
            Log::warning('d3_rechnung_abrufen failed', ['id' => $id, 'message' => $e->getMessage()]);

            return Response::error('Das D3-Dokument konnte nicht geladen werden: '.$e->getMessage());
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
            'pdf_herunterladen' => $schema->boolean()
                ->description('Wenn true, wird das PDF geladen und als Base64 zurückgegeben.')
                ->nullable(),
        ];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('D3-Dokument-ID.')
                ->required(),
            'display_name' => $schema->string()
                ->description('Anzeigename aus D3.')
                ->nullable(),
            'category' => $schema->string()
                ->description('Dokumentkategorie.')
                ->nullable(),
            'rechnungsnummer' => $schema->string()
                ->description('Rechnungsnummer (Property 60).')
                ->nullable(),
            'belegtyp' => $schema->string()
                ->description('Belegtyp (Property 82).')
                ->nullable(),
            'belegdatum' => $schema->string()
                ->description('Belegdatum (Property 8, falls vorhanden).')
                ->nullable(),
            'metadata' => $schema->object([
                'display_properties' => $schema->array()->items($schema->object([]))->required(),
                'system_properties' => $schema->array()->items($schema->object([]))->required(),
                'object_properties' => $schema->array()->items($schema->object([]))->required(),
            ])
                ->description('Roh-Metadaten aus D3.')
                ->required(),
            'pdf' => $schema->object([
                'mime_type' => $schema->string()->required(),
                'filename' => $schema->string()->required(),
                'content_base64' => $schema->string()->required(),
                'size_bytes' => $schema->integer()->required(),
            ])
                ->description('Optionales PDF als Base64, falls angefordert.')
                ->nullable(),
        ];
    }

    /**
     * @return array{mime_type: string, filename: string, content_base64: string, size_bytes: int}
     */
    private function downloadPdfAsBase64(D3Client $client, string $documentId): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'd3pdf_');
        if ($tempFile === false) {
            throw new \RuntimeException('Temporäre Datei für PDF konnte nicht erstellt werden.');
        }

        try {
            $success = $client->downloadDoc($documentId, $tempFile);
            if (! $success) {
                throw new \RuntimeException('PDF-Download aus D3 fehlgeschlagen.');
            }

            $binary = file_get_contents($tempFile);
            if ($binary === false) {
                throw new \RuntimeException('Heruntergeladenes PDF konnte nicht gelesen werden.');
            }

            return [
                'mime_type' => 'application/pdf',
                'filename' => $documentId.'.pdf',
                'content_base64' => base64_encode($binary),
                'size_bytes' => strlen($binary),
            ];
        } finally {
            @unlink($tempFile);
        }
    }

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
}
