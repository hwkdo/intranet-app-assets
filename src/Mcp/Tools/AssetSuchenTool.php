<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Tools;

use Hwkdo\IntranetAppAssets\Models\Asset;
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
class AssetSuchenTool extends Tool
{
    protected string $name = 'assets_suchen';

    protected string $description = 'Sucht Assets per Typesense und liefert strukturierte Basisdaten sowie einen direkten Link zur Asset-Detailseite.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $suchbegriff = trim((string) $request->get('suchbegriff', ''));
        Log::info('assets_suchen called', ['suchbegriff' => $suchbegriff]);
        if ($suchbegriff === '') {
            Log::warning('assets_suchen missing suchbegriff');
            return Response::error('Das Feld "suchbegriff" ist erforderlich.');
        }

        $assets = Asset::search($suchbegriff)
            ->query(fn ($builder) => $builder->with(['owner', 'type', 'vendor']))
            ->take(20)
            ->get();

        $result = $assets->map(function (Asset $asset): array {
            $url = route('apps.assets.show', $asset->id);

            return [
                'id' => $asset->id,
                'name' => $asset->name,
                'model' => $asset->model,
                'serial_number' => $asset->serial_number,
                'owner_name' => $asset->owner?->name,
                'type' => $asset->type?->name,
                'vendor' => $asset->vendor?->name,
                'location' => $asset->location,
                'itexia_id' => $asset->itexia_id,
                'url' => $url,
                'url_markdown' => sprintf('[Asset #%d](%s)', $asset->id, $url),
            ];
        })->values();
        Log::info('assets_suchen resolved', ['total' => $result->count()]);

        return Response::structured([
            'query' => $suchbegriff,
            'total' => $result->count(),
            'assets' => $result->all(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suchbegriff' => $schema->string()
                ->description('Freitext für die Typesense-Suche (z. B. Seriennummer, Benutzername, Modell, Itexia-ID).')
                ->required(),
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
                ->description('Anzahl gefundener Assets.')
                ->required(),
            'assets' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->integer()->description('Asset-ID.')->required(),
                    'name' => $schema->string()->description('Name des Assets.')->nullable(),
                    'model' => $schema->string()->description('Modellbezeichnung.')->nullable(),
                    'serial_number' => $schema->string()->description('Seriennummer.')->nullable(),
                    'owner_name' => $schema->string()->description('Name des Besitzers.')->nullable(),
                    'type' => $schema->string()->description('Asset-Typ.')->nullable(),
                    'vendor' => $schema->string()->description('Hersteller.')->nullable(),
                    'location' => $schema->string()->description('Standort.')->nullable(),
                    'itexia_id' => $schema->string()->description('Itexia-ID.')->nullable(),
                    'url' => $schema->string()->description('Direkter Link zur Asset-Detailseite.')->required(),
                    'url_markdown' => $schema->string()->description('Markdown-Link zur Asset-Detailseite.')->required(),
                ]))
                ->description('Trefferliste mit strukturierten Asset-Basisdaten.')
                ->required(),
        ];
    }
}
