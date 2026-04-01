<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Tools;

use Hwkdo\IntranetAppAssets\Models\AssetType;
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
class AssetTypenAuflistenTool extends Tool
{
    protected string $name = 'asset_typen_auflisten';

    protected string $description = 'Listet verfügbare Asset-Typen mit ID und Typ-Eigenschaften auf, damit die korrekte asset_type_id für asset_anlegen gewählt werden kann.';

    public function handle(Request $request): Response|ResponseFactory
    {
        Log::info('asset_typen_auflisten called', ['input' => $request->all()]);

        $types = AssetType::query()
            ->select(['id', 'name', 'is_domain_object', 'itexia_creation_allowed'])
            ->orderBy('name')
            ->limit(200)
            ->get();
        Log::info('asset_typen_auflisten resolved', ['total' => $types->count()]);

        return Response::structured([
            'total' => $types->count(),
            'types' => $types->map(fn (AssetType $type): array => [
                'id' => $type->id,
                'name' => (string) $type->name,
                'is_domain_object' => (bool) $type->is_domain_object,
                'itexia_creation_allowed' => (bool) $type->itexia_creation_allowed,
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'total' => $schema->integer()
                ->description('Anzahl zurückgegebener Asset-Typen.')
                ->required(),
            'types' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->integer()->description('Eindeutige Asset-Typ-ID (asset_type_id).')->required(),
                    'name' => $schema->string()->description('Name des Asset-Typs.')->required(),
                    'is_domain_object' => $schema->boolean()->description('Kennzeichnet, ob der Typ ein Domain-Objekt ist.')->required(),
                    'itexia_creation_allowed' => $schema->boolean()->description('Kennzeichnet, ob Itexia-Anlage für diesen Typ erlaubt ist.')->required(),
                ]))
                ->description('Liste der verfügbaren Asset-Typen für die Auswahl der asset_type_id.')
                ->required(),
        ];
    }
}
