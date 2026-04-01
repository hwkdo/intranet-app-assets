<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Tools;

use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
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
class D3RechnungSuchenTool extends Tool
{
    protected string $name = 'd3_rechnung_suchen';

    protected string $description = 'Sucht Rechnungsdokumente (Zahlungsbelege) im D3 DMS und liefert Treffer inklusive Dokument-ID (T-Nummer), relevanter Properties und Deep-Link.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $suchbegriff = trim((string) $request->get('suchbegriff', ''));
        $limit = min(100, max(1, (int) $request->get('limit', 50)));

        if ($suchbegriff === '') {
            return Response::error('Das Feld "suchbegriff" ist erforderlich.');
        }

        if (! class_exists(D3Client::class)) {
            return Response::error('D3-REST-Client ist nicht verfügbar.');
        }

        Log::info('d3_rechnung_suchen called', ['suchbegriff' => $suchbegriff, 'limit' => $limit]);

        try {
            $client = app(D3Client::class);
            $raw = $client->SearchResult($suchbegriff, DocTypeEnum::Zahlungsbeleg, null, $limit, true);
            $items = is_array($raw) ? ($raw['items'] ?? []) : [];
            $baseUrl = rtrim(D3Client::getBaseUrl(), '/');

            $results = collect($items)->map(function (mixed $item) use ($baseUrl): array {
                if (! is_array($item)) {
                    return [];
                }

                $id = (string) ($item['id'] ?? '');
                $href = $item['_links']['details']['href'] ?? null;
                $link = is_string($href) ? $baseUrl.'/'.ltrim($href, '/') : null;

                return [
                    'id' => $id !== '' ? $id : null,
                    'display_name' => $item['caption'] ?? null,
                    'category' => $item['category']['name'] ?? null,
                    'belegtyp' => $this->getDisplayProperty($item, '82'),
                    'rechnungsnummer' => $this->getDisplayProperty($item, '60') ?? ($id !== '' ? $id : null),
                    'belegdatum' => $this->getDisplayProperty($item, '8'),
                    'link' => $link,
                ];
            })->filter(fn (array $item): bool => ! empty($item['id']))->values();

            return Response::structured([
                'query' => $suchbegriff,
                'total' => $results->count(),
                'results' => $results->all(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('d3_rechnung_suchen failed', [
                'suchbegriff' => $suchbegriff,
                'message' => $e->getMessage(),
            ]);

            return Response::error('Die D3-Suche ist fehlgeschlagen: '.$e->getMessage());
        }
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suchbegriff' => $schema->string()
                ->description('Volltext-Suchbegriff für D3 (z. B. BEN, Seriennummer, IMEI, Rechnungsnummer).')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximale Anzahl Treffer (1-100, Standard 50).')
                ->nullable(),
        ];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Der übergebene Suchbegriff.')
                ->required(),
            'total' => $schema->integer()
                ->description('Anzahl gefundener D3-Treffer.')
                ->required(),
            'results' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->string()->description('D3-Dokument-ID (T-Nummer).')->required(),
                    'display_name' => $schema->string()->description('Anzeigename aus D3.')->nullable(),
                    'category' => $schema->string()->description('Dokumentkategorie.')->nullable(),
                    'belegtyp' => $schema->string()->description('Belegtyp (Property 82).')->nullable(),
                    'rechnungsnummer' => $schema->string()->description('Rechnungsnummer (Property 60).')->nullable(),
                    'belegdatum' => $schema->string()->description('Belegdatum (Property 8, falls vorhanden).')->nullable(),
                    'link' => $schema->string()->description('Direkter D3-Link zur Detailansicht.')->nullable(),
                ]))
                ->description('Trefferliste aus D3.')
                ->required(),
        ];
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
