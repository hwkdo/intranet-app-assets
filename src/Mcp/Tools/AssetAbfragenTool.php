<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Tools;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld]
class AssetAbfragenTool extends Tool
{
    protected string $name = 'assets_abfragen';

    protected string $description = 'Filtert Assets per Eloquent-Abfrage nach strukturierten Kriterien (Besitzer, BEN, Rechnung, Typ, Hersteller, Bulk-Seriennummern, gecachte Itexia-Räume, …). WICHTIG zu Itexia: «in Itexia gefunden», «mit Itexia-UUID» → found_in_itexia=true (itexia_uuid gesetzt). Nur itexia_id ohne UUID ist nicht «gefunden». «Ohne Itexia-Ist-Raum» / fehlende Raumzuordnung laut Sync-Datenbank: found_in_itexia=true und itexia_actual_room_missing=true (itexia_actual_room_id IS NULL). Optional itexia_target_room_missing für fehlenden Soll-Raum. Exakte Raum-IDs: itexia_actual_room_id / itexia_target_room_id (Integer). Gecachte Felder können bis zum nächsten Sync veraltet sein; Live-Abgleich: itexia_raum_intranet_pruefen. «location» ist nur Intranet-Standorttext. serial_numbers als echtes JSON-Array.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $rawSerialNumbers = $request->get('serial_numbers');
        if (is_string($rawSerialNumbers)) {
            $decoded = json_decode($rawSerialNumbers, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['serial_numbers' => $decoded]);
            }
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'serial_numbers' => ['nullable', 'array', 'max:500'],
            'serial_numbers.*' => ['string', 'max:255'],
            'asset_type_id' => ['nullable', 'integer', 'exists:intranet_app_assets_asset_types,id'],
            'asset_vendor_id' => ['nullable', 'integer', 'exists:intranet_app_assets_asset_vendors,id'],
            'invoice_number_pending' => ['nullable', 'boolean'],
            'found_in_itexia' => ['nullable', 'boolean'],
            'itexia_actual_room_missing' => ['nullable', 'boolean'],
            'itexia_target_room_missing' => ['nullable', 'boolean'],
            'itexia_actual_room_id' => ['nullable', 'integer'],
            'itexia_target_room_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        if ($rawSerialNumbers !== null && ! is_array($request->get('serial_numbers'))) {
            return Response::error('Das Feld "serial_numbers" muss ein Array von Strings sein oder ein JSON-Array als String.');
        }

        $limit = isset($validated['limit']) ? (int) $validated['limit'] : 20;
        Log::info('assets_abfragen called', ['filters' => $validated, 'limit' => $limit]);

        $normalizedSerialNumbers = $this->normalizedSerialNumberList($validated['serial_numbers'] ?? null);
        if ($normalizedSerialNumbers === [] && isset($validated['serial_number']) && is_string($validated['serial_number'])) {
            $singleNormalized = $this->normalizeSerialNumber($validated['serial_number']);
            if ($singleNormalized !== null) {
                $normalizedSerialNumbers = [$singleNormalized];
            }
        }

        $query = Asset::query()->with(['owner', 'type', 'vendor']);
        $query = $this->applyFilters($query, $validated, $normalizedSerialNumbers);

        $assetsQuery = $query->latest('id');
        if ($normalizedSerialNumbers === []) {
            $assetsQuery->limit($limit);
        }

        $assets = $assetsQuery->get();

        $result = $assets->map(function (Asset $asset): array {
            $url = route('apps.assets.show', $asset->id);
            $foundInItexia = self::assetHasItexiaUuid($asset);

            return [
                'id' => $asset->id,
                'name' => $asset->name,
                'model' => $asset->model,
                'serial_number' => $asset->serial_number,
                'owner_name' => $asset->owner?->name,
                'type' => $asset->type?->name,
                'vendor' => $asset->vendor?->name,
                'location' => $asset->location,
                'order_number' => $asset->order_number,
                'invoice_number' => $asset->invoice_number,
                'invoice_number_pending' => (bool) $asset->invoice_number_pending,
                'itexia_id' => $asset->itexia_id,
                'itexia_uuid' => $asset->itexia_uuid,
                'itexia_actual_room_id' => $asset->itexia_actual_room_id,
                'itexia_target_room_id' => $asset->itexia_target_room_id,
                'itexia_rooms_synced_at' => $asset->itexia_rooms_synced_at?->toIso8601String(),
                'found_in_itexia' => $foundInItexia,
                'url' => $url,
                'url_markdown' => sprintf('[Asset #%d](%s)', $asset->id, $url),
            ];
        })->values();

        $matchedSerialNumbers = [];
        $missingSerialNumbers = [];
        if ($normalizedSerialNumbers !== []) {
            $matched = [];
            foreach ($assets as $asset) {
                $normalized = $this->normalizeSerialNumber((string) ($asset->serial_number ?? ''));
                if ($normalized !== null) {
                    $matched[$normalized] = true;
                }
            }

            $matchedSerialNumbers = collect($normalizedSerialNumbers)
                ->filter(fn (string $value): bool => isset($matched[$value]))
                ->values()
                ->all();
            $missingSerialNumbers = collect($normalizedSerialNumbers)
                ->reject(fn (string $value): bool => isset($matched[$value]))
                ->values()
                ->all();
        }

        return Response::structured([
            'filters' => [
                'user_id' => $validated['user_id'] ?? null,
                'owner_name' => $validated['owner_name'] ?? null,
                'order_number' => $validated['order_number'] ?? null,
                'invoice_number' => $validated['invoice_number'] ?? null,
                'serial_number' => $validated['serial_number'] ?? null,
                'serial_numbers' => $validated['serial_numbers'] ?? null,
                'serial_numbers_normalized' => $normalizedSerialNumbers === [] ? null : $normalizedSerialNumbers,
                'asset_type_id' => $validated['asset_type_id'] ?? null,
                'asset_vendor_id' => $validated['asset_vendor_id'] ?? null,
                'invoice_number_pending' => $validated['invoice_number_pending'] ?? null,
                'found_in_itexia' => $validated['found_in_itexia'] ?? null,
                'itexia_actual_room_missing' => $validated['itexia_actual_room_missing'] ?? null,
                'itexia_target_room_missing' => $validated['itexia_target_room_missing'] ?? null,
                'itexia_actual_room_id' => $validated['itexia_actual_room_id'] ?? null,
                'itexia_target_room_id' => $validated['itexia_target_room_id'] ?? null,
                'limit' => $limit,
            ],
            'total' => $result->count(),
            'total_requested_serial_numbers' => $normalizedSerialNumbers === [] ? null : count($normalizedSerialNumbers),
            'matched_serial_numbers' => $normalizedSerialNumbers === [] ? null : $matchedSerialNumbers,
            'missing_serial_numbers' => $normalizedSerialNumbers === [] ? null : $missingSerialNumbers,
            'assets' => $result->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $normalizedSerialNumbers
     */
    private function applyFilters(Builder $query, array $filters, array $normalizedSerialNumbers): Builder
    {
        if (isset($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (isset($filters['owner_name']) && is_string($filters['owner_name'])) {
            $ownerName = trim($filters['owner_name']);
            if ($ownerName !== '') {
                $query->whereHas('owner', function (Builder $ownerQuery) use ($ownerName): void {
                    $ownerQuery->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($ownerName).'%']);
                });
            }
        }

        if (isset($filters['order_number']) && is_string($filters['order_number'])) {
            $query->where('order_number', trim($filters['order_number']));
        }

        if (isset($filters['invoice_number']) && is_string($filters['invoice_number'])) {
            $query->where('invoice_number', trim($filters['invoice_number']));
        }

        if ($normalizedSerialNumbers !== []) {
            $query->whereRaw(
                "UPPER(REPLACE(REPLACE(serial_number, '-', ''), ' ', '')) IN (".implode(',', array_fill(0, count($normalizedSerialNumbers), '?')).')',
                $normalizedSerialNumbers
            );
        }

        if (isset($filters['asset_type_id'])) {
            $query->where('asset_type_id', (int) $filters['asset_type_id']);
        }

        if (isset($filters['asset_vendor_id'])) {
            $query->where('asset_vendor_id', (int) $filters['asset_vendor_id']);
        }

        if (isset($filters['invoice_number_pending'])) {
            $query->where('invoice_number_pending', (bool) $filters['invoice_number_pending']);
        }

        if (isset($filters['found_in_itexia'])) {
            if ((bool) $filters['found_in_itexia']) {
                $query->whereNotNull('itexia_uuid')->where('itexia_uuid', '!=', '');
            } else {
                $query->where(function (Builder $q): void {
                    $q->whereNull('itexia_uuid')->orWhere('itexia_uuid', '');
                });
            }
        }

        if (isset($filters['itexia_actual_room_missing']) && (bool) $filters['itexia_actual_room_missing']) {
            $query->whereNull('itexia_actual_room_id');
        }

        if (isset($filters['itexia_target_room_missing']) && (bool) $filters['itexia_target_room_missing']) {
            $query->whereNull('itexia_target_room_id');
        }

        if (array_key_exists('itexia_actual_room_id', $filters) && $filters['itexia_actual_room_id'] !== null) {
            $query->where('itexia_actual_room_id', (int) $filters['itexia_actual_room_id']);
        }

        if (array_key_exists('itexia_target_room_id', $filters) && $filters['itexia_target_room_id'] !== null) {
            $query->where('itexia_target_room_id', (int) $filters['itexia_target_room_id']);
        }

        return $query;
    }

    /**
     * True, wenn das Objekt in Itexia/Seventhings gesucht und die UUID gespeichert wurde (itexia_uuid).
     * Nur itexia_id (Barcode) ohne UUID zählt nicht als «gefunden».
     */
    private static function assetHasItexiaUuid(Asset $asset): bool
    {
        $uuid = $asset->itexia_uuid;

        return $uuid !== null && trim((string) $uuid) !== '';
    }

    private function normalizeSerialNumber(?string $serialNumber): ?string
    {
        if (! is_string($serialNumber)) {
            return null;
        }

        $trimmed = trim($serialNumber);
        if ($trimmed === '') {
            return null;
        }

        return mb_strtoupper(str_replace([' ', '-'], '', $trimmed));
    }

    /**
     * @param  mixed  $serialNumbers
     * @return array<int, string>
     */
    private function normalizedSerialNumberList(mixed $serialNumbers): array
    {
        if (! is_array($serialNumbers)) {
            return [];
        }

        return collect($serialNumbers)
            ->filter(fn (mixed $value): bool => is_string($value))
            ->map(fn (string $value): ?string => $this->normalizeSerialNumber($value))
            ->filter(fn (?string $value): bool => $value !== null)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()
                ->description('Filtert nach exakter Besitzer-ID.')
                ->nullable(),
            'owner_name' => $schema->string()
                ->description('Fuzzy-Filter auf den Besitzer-Namen (users.name).')
                ->nullable(),
            'order_number' => $schema->string()
                ->description('Filtert nach exakter Bestellnummer (BEN).')
                ->nullable(),
            'invoice_number' => $schema->string()
                ->description('Filtert nach exakter Rechnungsnummer.')
                ->nullable(),
            'serial_number' => $schema->string()
                ->description('Filtert nach einzelner Seriennummer (normalisiert: Großbuchstaben, ohne Leerzeichen/Bindestriche).')
                ->nullable(),
            'serial_numbers' => $schema->array()
                ->items($schema->string())
                ->description('Bulk-Filter für mehrere Seriennummern. MUSS ein echtes JSON-Array sein, z. B. ["5CD31688CH","5CD31688CV"]. Kein String mit eingebettetem JSON verwenden. Alle Werte werden normalisiert (Großbuchstaben, ohne Leerzeichen/Bindestriche).')
                ->nullable(),
            'asset_type_id' => $schema->integer()
                ->description('Filtert nach Asset-Typ-ID.')
                ->nullable(),
            'asset_vendor_id' => $schema->integer()
                ->description('Filtert nach Hersteller-ID.')
                ->nullable(),
            'invoice_number_pending' => $schema->boolean()
                ->description('Filtert auf Assets mit/ohne offene Rechnungsnummer.')
                ->nullable(),
            'found_in_itexia' => $schema->boolean()
                ->description('Pflicht bei Nutzerformulierungen wie «in Itexia gefunden», «mit Itexia-UUID», «in Seventhings»: immer true setzen. true = itexia_uuid gesetzt (wirklich synchronisiert); false = keine UUID. Ohne diesen Filter: beliebige Assets — nicht mit «gefunden in Itexia» verwechseln.')
                ->nullable(),
            'itexia_actual_room_missing' => $schema->boolean()
                ->description('true = nur Assets, bei denen itexia_actual_room_id NULL ist (kein gecachter Ist-Raum laut Sync). Typisch zusammen mit found_in_itexia=true für «in Itexia bekannt, aber ohne Ist-Raum-Zuordnung in der DB».')
                ->nullable(),
            'itexia_target_room_missing' => $schema->boolean()
                ->description('true = nur Assets mit itexia_target_room_id NULL (kein gecachter Soll-Raum).')
                ->nullable(),
            'itexia_actual_room_id' => $schema->integer()
                ->description('Exakter Filter auf gecachte Seventhings-Ist-Raum-ID (actual_room). Nicht kombinieren mit itexia_actual_room_missing=true.')
                ->nullable(),
            'itexia_target_room_id' => $schema->integer()
                ->description('Exakter Filter auf gecachte Seventhings-Soll-Raum-ID (target_room).')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Maximale Anzahl Treffer (1-500, Standard 20). Bei serial_numbers wird kein zusätzliches Limit erzwungen.')
                ->nullable(),
        ];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'filters' => $schema->object([
                'user_id' => $schema->integer()->nullable(),
                'owner_name' => $schema->string()->nullable(),
                'order_number' => $schema->string()->nullable(),
                'invoice_number' => $schema->string()->nullable(),
                'serial_number' => $schema->string()->nullable(),
                'serial_numbers' => $schema->array()->items($schema->string())->nullable(),
                'serial_numbers_normalized' => $schema->array()->items($schema->string())->nullable(),
                'asset_type_id' => $schema->integer()->nullable(),
                'asset_vendor_id' => $schema->integer()->nullable(),
                'invoice_number_pending' => $schema->boolean()->nullable(),
                'found_in_itexia' => $schema->boolean()->nullable(),
                'itexia_actual_room_missing' => $schema->boolean()->nullable(),
                'itexia_target_room_missing' => $schema->boolean()->nullable(),
                'itexia_actual_room_id' => $schema->integer()->nullable(),
                'itexia_target_room_id' => $schema->integer()->nullable(),
                'limit' => $schema->integer()->required(),
            ])
                ->description('Die tatsächlich angewendeten Filter. Wenn serial_numbers gesetzt wurde, enthält serial_numbers_normalized die finale Vergleichsmenge.')
                ->required(),
            'total' => $schema->integer()
                ->description('Anzahl gefundener Assets.')
                ->required(),
            'total_requested_serial_numbers' => $schema->integer()
                ->description('Anzahl angefragter Seriennummern nach Normalisierung.')
                ->nullable(),
            'matched_serial_numbers' => $schema->array()
                ->items($schema->string())
                ->description('Normalisierte Seriennummern, die im Bestand gefunden wurden.')
                ->nullable(),
            'missing_serial_numbers' => $schema->array()
                ->items($schema->string())
                ->description('Normalisierte Seriennummern, die nicht im Bestand gefunden wurden.')
                ->nullable(),
            'assets' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->integer()->description('Asset-ID.')->required(),
                    'name' => $schema->string()->description('Name des Assets.')->nullable(),
                    'model' => $schema->string()->description('Modellbezeichnung.')->nullable(),
                    'serial_number' => $schema->string()->description('Seriennummer.')->nullable(),
                    'owner_name' => $schema->string()->description('Name des Besitzers.')->nullable(),
                    'type' => $schema->string()->description('Asset-Typ.')->nullable(),
                    'vendor' => $schema->string()->description('Hersteller.')->nullable(),
                    'location' => $schema->string()->description('Intranet-Standorttext (Freitext), nicht der Itexia-Ist-Raum (actual_room).')->nullable(),
                    'order_number' => $schema->string()->description('Bestellnummer (BEN).')->nullable(),
                    'invoice_number' => $schema->string()->description('Rechnungsnummer.')->nullable(),
                    'invoice_number_pending' => $schema->boolean()->description('Kennzeichnet offene Rechnungsnummer.')->required(),
                    'itexia_id' => $schema->string()->description('Itexia-Barcode/ID (geplant); allein kein Nachweis für «gefunden in Itexia».')->nullable(),
                    'itexia_uuid' => $schema->string()->description('Seventhings-Objekt-UUID nach erfolgreicher Suche; null = noch nicht in Itexia gefunden/synchronisiert.')->nullable(),
                    'itexia_actual_room_id' => $schema->integer()->description('Gecachter Seventhings-Ist-Raum (actual_room), zuletzt per Sync/PATCH aktualisiert; null = noch nicht synchronisiert.')->nullable(),
                    'itexia_target_room_id' => $schema->integer()->description('Gecachter Seventhings-Soll-Raum (target_room); null = nicht gesetzt oder noch nicht synchronisiert.')->nullable(),
                    'itexia_rooms_synced_at' => $schema->string()->description('Zeitpunkt der letzten Raum-Synchronisation (ISO-8601) oder null.')->nullable(),
                    'found_in_itexia' => $schema->boolean()->description('Kurz: itexia_uuid gesetzt (true) oder nicht (false).')->required(),
                    'url' => $schema->string()->description('Direkter Link zur Asset-Detailseite.')->required(),
                    'url_markdown' => $schema->string()->description('Markdown-Link zur Asset-Detailseite.')->required(),
                ]))
                ->description('Trefferliste mit strukturierten Asset-Daten.')
                ->required(),
        ];
    }
}
