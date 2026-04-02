<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Tools;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Services\AssetItexiaRoomSearchHintResolver;
use Hwkdo\SeventhingsLaravel\SeventhingsLaravel;
use Hwkdo\SeventhingsLaravel\Support\ItexiaRoomInspection;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Lädt den Ist-Raum aus Itexia; room_search_hint wird aus dem Intranet-Asset (itexia_id = barcode) abgeleitet:
 * Besitzer-Raum hat Vorrang, sonst Asset-Standort (location).
 */
#[IsReadOnly]
#[IsOpenWorld]
class ItexiaRaumIntranetPruefenTool extends Tool
{
    protected string $name = 'itexia_raum_intranet_pruefen';

    protected string $description = 'Lädt ein Itexia-Objekt per Barcode und vergleicht den Ist-Raum mit dem Ziel aus dem Intranet-Asset: zuerst Raum des Besitzers (owner.raum), sonst Standort des Assets (location). Liefert owner_name (Vor- und Nachname des Intranet-Besitzers), wenn gesetzt. Bei genau einem passenden Itexia-Raum: expected_actual_room_id und should_update wie gewohnt. Bei mehreren Treffern: expected_actual_room_id null, expected_ambiguous true, matching_rooms enthält alle Kandidaten (id, name, label, nummer) — Nutzer wählt einen Raum, dann itexia_raum_aktualisieren mit actual_room_id. Folgeschritt bei gewünschter Aktualisierung: itexia_raum_aktualisieren per Tool aufrufen, kein JSON an den Nutzer.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        Gate::forUser($user)->authorize('see-app-assets');

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
        ]);

        $barcode = trim($validated['barcode']);
        if ($barcode === '') {
            return Response::error('Der Barcode darf nicht leer sein.');
        }

        if (! class_exists(SeventhingsLaravel::class) || ! app()->bound(SeventhingsLaravel::class)) {
            return Response::error('Seventhings/Itexia ist in dieser Umgebung nicht gebunden (Paket oder Konfiguration fehlt).');
        }

        $asset = Asset::query()->where('itexia_id', $barcode)->first();

        $roomSearchHint = '';
        $targetSource = AssetItexiaRoomSearchHintResolver::SOURCE_NONE;
        $ownerName = null;
        if ($asset !== null) {
            $asset->loadMissing('owner');
            $resolved = AssetItexiaRoomSearchHintResolver::resolve($asset);
            $roomSearchHint = $resolved['hint'] ?? '';
            $targetSource = $resolved['source'] ?? AssetItexiaRoomSearchHintResolver::SOURCE_NONE;
            $owner = $asset->owner;
            if ($owner !== null) {
                $candidate = trim((string) $owner->name);
                $ownerName = $candidate !== '' ? $candidate : null;
            }
        }

        Log::info('itexia_raum_intranet_pruefen called', [
            'barcode' => $barcode,
            'asset_found' => $asset !== null,
            'target_source' => $targetSource,
            'has_hint' => $roomSearchHint !== '',
        ]);

        /** @var \Hwkdo\SeventhingsLaravel\Client $client */
        $client = app()->make(SeventhingsLaravel::class);

        try {
            $payload = ItexiaRoomInspection::inspect($client, $barcode, $roomSearchHint);
        } catch (\Throwable $e) {
            Log::error('itexia_raum_intranet_pruefen api error', ['message' => $e->getMessage()]);

            return Response::error('Itexia-Abfrage fehlgeschlagen: '.$e->getMessage());
        }

        $expectedId = $payload['expected_actual_room_id'] ?? null;
        $currentId = $payload['current_actual_room_id'] ?? null;

        $shouldUpdate = false;
        if ($expectedId !== null) {
            $shouldUpdate = $currentId === null || $currentId !== $expectedId;
        }

        return Response::structured(array_merge($payload, [
            'intranet_asset_found' => $asset !== null,
            'target_room_source' => $targetSource,
            'owner_name' => $ownerName,
            'should_update' => $shouldUpdate,
        ]));
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'barcode' => $schema->string()
                ->description('Itexia-Barcode / itexia_id des Intranet-Assets.')
                ->required(),
        ];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'barcode' => $schema->string()->required(),
            'found' => $schema->boolean()->required(),
            'message' => $schema->string()->nullable()->description('Nur bei found=false: Hinweistext.'),
            'object_uuid' => $schema->string()->nullable(),
            'current_actual_room_id' => $schema->integer()->nullable(),
            'current_actual_room_label' => $schema->string()->nullable(),
            'room_search_hint' => $schema->string()->nullable(),
            'expected_actual_room_id' => $schema->integer()->nullable(),
            'expected_resolved' => $schema->boolean()->nullable(),
            'expected_ambiguous' => $schema->boolean()->nullable()->description('true, wenn mehrere Itexia-Räume zum Suchbegriff passen.'),
            'matching_rooms' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->integer()->required(),
                    'name' => $schema->string()->required(),
                    'label' => $schema->string()->required(),
                    'nummer' => $schema->string()->required(),
                ]))
                ->nullable()
                ->description('Alle passenden Räume in Itexia (bei Hint gesetzt; leeres Array = kein Treffer).'),
            'rooms_match' => $schema->boolean()->nullable(),
            'intranet_asset_found' => $schema->boolean()->required(),
            'target_room_source' => $schema->string()->required(),
            'owner_name' => $schema->string()
                ->nullable()
                ->description('Vor- und Nachname des Intranet-Besitzers (owner), wenn das Asset einen Besitzer hat; sonst null.'),
            'should_update' => $schema->boolean()->required(),
        ];
    }
}
