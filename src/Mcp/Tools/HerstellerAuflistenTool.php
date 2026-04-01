<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Tools;

use Hwkdo\IntranetAppAssets\Models\AssetVendor;
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
class HerstellerAuflistenTool extends Tool
{
    protected string $name = 'hersteller_auflisten';

    protected string $description = 'Listet Hersteller (AssetVendor) inklusive ID auf, damit die korrekte asset_vendor_id für asset_anlegen verwendet werden kann.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $filter = trim((string) $request->get('filter', ''));
        Log::info('hersteller_auflisten called', ['filter' => $filter !== '' ? $filter : null]);

        $query = AssetVendor::query()
            ->select(['id', 'name'])
            ->orderBy('name');

        if ($filter !== '') {
            $query->where('name', 'like', '%'.$filter.'%');
        }

        $vendors = $query->limit(200)->get();
        Log::info('hersteller_auflisten resolved', ['total' => $vendors->count()]);

        return Response::structured([
            'filter' => $filter !== '' ? $filter : null,
            'total' => $vendors->count(),
            'vendors' => $vendors->map(fn (AssetVendor $vendor): array => [
                'id' => $vendor->id,
                'name' => (string) $vendor->name,
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()
                ->description('Optionaler Namensfilter für Hersteller. Beispiel: "Lenovo" oder "Apple".')
                ->nullable(),
        ];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()
                ->description('Verwendeter Namensfilter; null wenn kein Filter übergeben wurde.')
                ->nullable(),
            'total' => $schema->integer()
                ->description('Anzahl zurückgegebener Hersteller.')
                ->required(),
            'vendors' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->integer()->description('Eindeutige Hersteller-ID (asset_vendor_id).')->required(),
                    'name' => $schema->string()->description('Anzeigename des Herstellers.')->required(),
                ]))
                ->description('Herstellerliste für die Auswahl der asset_vendor_id.')
                ->required(),
        ];
    }
}
