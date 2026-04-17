<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Tools;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Contracts\OrderNumberValidationServiceInterface;
use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Services\D3InvoiceValidationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class AssetAnlegenTool extends Tool
{
    protected string $name = 'asset_anlegen';

    protected string $description = 'Legt ein Asset an und folgt einer klaren Prioritätslogik: Bei herkunft="bestellung" ist order_number das primäre Pflichtfeld, wert ist dann optional. IDs (asset_type_id, asset_vendor_id, user_id) sind bevorzugt; falls sie fehlen, können Typ/Hersteller/Besitzer aus Text aufgelöst werden oder es werden klare nächste Tool-Schritte zurückgegeben.';

    public function handle(Request $request): Response|ResponseFactory
    {
        Log::info('asset_anlegen called', [
            'input' => $request->all(),
            'auth_user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        $validated = $request->validate([
            'herkunft' => ['required', 'string', 'in:bestellung,beschaffung'],
            'wert' => ['nullable', 'numeric', 'min:0'],
            'model' => ['required', 'string', 'max:255'],
            'serial_number' => ['required', 'string', 'max:255'],
            'asset_type_id' => ['nullable', 'integer', 'exists:intranet_app_assets_asset_types,id'],
            'asset_vendor_id' => ['nullable', 'integer', 'exists:intranet_app_assets_asset_vendors,id'],
            'asset_type' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'typ' => ['nullable', 'string', 'max:255'],
            'kategorie' => ['nullable', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'hersteller' => ['nullable', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'itexia_id' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'owner' => ['nullable', 'string', 'max:255'],
            'besitzer_query' => ['nullable', 'string', 'max:255'],
            'besitzer' => ['nullable', 'array'],
            'besitzer.name' => ['nullable', 'string', 'max:255'],
            'besitzer.vorname' => ['nullable', 'string', 'max:255'],
            'besitzer.nachname' => ['nullable', 'string', 'max:255'],
            'besitzer.username' => ['nullable', 'string', 'max:255'],
            'besitzer.email' => ['nullable', 'email', 'max:255'],
        ], [
            'herkunft.in' => 'Das Feld "herkunft" muss entweder "bestellung" oder "beschaffung" sein.',
            'user_id.exists' => 'Die angegebene user_id wurde nicht gefunden. Bitte benutzer_suchen nutzen und eine gültige ID übernehmen.',
            'besitzer.email.email' => 'Das Feld "besitzer.email" muss eine gültige E-Mail-Adresse sein.',
        ]);

        $appSettings = $this->appSettings();
        $wert = isset($validated['wert']) ? (float) $validated['wert'] : null;
        $wertgrenze = (float) $appSettings->wertgrenzeItexia;
        $isOverThreshold = $wert !== null ? $wert >= $wertgrenze : null;

        if ($validated['herkunft'] === 'bestellung' && empty($validated['order_number'])) {
            return $this->errorWithLog('herkunft=\'bestellung\' erfordert eine Bestellnummer (BEN/order_number), da das Asset über den Intranet-Bestellprozess beschafft wurde.', $validated);
        }

        if ($validated['herkunft'] === 'beschaffung' && empty($validated['invoice_number'])) {
            return $this->errorWithLog('herkunft=\'beschaffung\' erfordert eine Rechnungsnummer (invoice_number), da kein Intranet-Bestellvorgang existiert.', $validated);
        }

        if ($validated['herkunft'] === 'bestellung' && filled($validated['order_number'] ?? null)) {
            /** @var OrderNumberValidationServiceInterface $orderValidationService */
            $orderValidationService = app(OrderNumberValidationServiceInterface::class);
            $orderValidationError = $orderValidationService->getValidationError((string) $validated['order_number']);
            if ($orderValidationError !== null) {
                return $this->errorWithLog(
                    $orderValidationError.' Bitte zuerst bestellung_pruefen aufrufen und eine gültige Bestellnummer übergeben.',
                    $validated
                );
            }
        }

        if ($validated['herkunft'] === 'beschaffung' && filled($validated['invoice_number'] ?? null)) {
            /** @var D3InvoiceValidationService $invoiceValidationService */
            $invoiceValidationService = app(D3InvoiceValidationService::class);
            $invoiceValidationError = $invoiceValidationService->getValidationError((string) $validated['invoice_number']);
            if ($invoiceValidationError !== null) {
                return $this->errorWithLog(
                    $invoiceValidationError.' Wenn nur Rechnungsnummer/Freitext bekannt: d3_rechnung_suchen zur Ermittlung der T-Nummer; wenn schon T… bekannt: keine Suche — für OCR-Felder d3_rechnung_analysieren und invoice_number als T… setzen.',
                    $validated
                );
            }
        }

        if ($isOverThreshold === true && empty($validated['itexia_id'])) {
            return $this->errorWithLog(sprintf(
                'Der Wert (%.2f€) liegt über der Wertgrenze (%.2f€). Bitte die Itexia-ID (itexia_id) angeben - ab dieser Grenze ist Inventarisierung in Itexia/Seventhings Pflicht.',
                (float) $wert,
                $wertgrenze
            ), $validated);
        }

        $assetTypeId = $this->resolveAssetTypeId($validated);
        if ($assetTypeId === null) {
            $typeQuery = $validated['asset_type'] ?? $validated['type'] ?? $validated['typ'] ?? $validated['kategorie'] ?? null;

            return $this->errorWithLog(
                'asset_type_id fehlt oder ist nicht eindeutig auflösbar. Bitte zuerst asset_typen_auflisten aufrufen'.($typeQuery ? ' (z. B. mit Auswahl für "'.$typeQuery.'")' : '').' und die korrekte ID übergeben.',
                $validated
            );
        }

        $assetVendorId = $this->resolveAssetVendorId($validated);
        if ($assetVendorId === null) {
            $vendorQuery = $validated['hersteller'] ?? $validated['vendor'] ?? null;

            return $this->errorWithLog(
                'asset_vendor_id fehlt oder ist nicht eindeutig auflösbar. Bitte zuerst hersteller_auflisten aufrufen'.($vendorQuery ? ' (z. B. filter="'.$vendorQuery.'")' : '').' und die korrekte ID übergeben.',
                $validated
            );
        }

        $besitzer = is_array($validated['besitzer'] ?? null) ? $validated['besitzer'] : null;
        $ownerQuery = $this->resolveOwnerQuery($validated, $besitzer);
        $userId = $validated['user_id'] ?? null;

        if ($userId === null && $ownerQuery !== null) {
            $resolvedUserId = $this->resolveUniqueUserId($ownerQuery);

            if ($resolvedUserId === null) {
                return $this->errorWithLog(
                    'Besitzer konnte nicht eindeutig aufgelöst werden. Bitte benutzer_suchen mit suchbegriff="'.$ownerQuery.'" aufrufen und die ermittelte user_id in asset_anlegen übergeben.',
                    $validated
                );
            }

            $userId = $resolvedUserId;
        }

        if ($userId === null && ($validated['location'] ?? null) === null) {
            return $this->errorWithLog('Es muss entweder ein Besitzer (user_id, via benutzer_suchen ermitteln) oder ein Standort (location) angegeben werden.', $validated);
        }

        $asset = Asset::create([
            'model' => $validated['model'],
            'asset_type_id' => $assetTypeId,
            'asset_vendor_id' => $assetVendorId,
            'serial_number' => $validated['serial_number'],
            'name' => $validated['name'] ?? null,
            'location' => $validated['location'] ?? null,
            'user_id' => $userId,
            'order_number' => $validated['herkunft'] === 'bestellung' ? ($validated['order_number'] ?? null) : null,
            'invoice_number' => $validated['herkunft'] === 'beschaffung' ? ($validated['invoice_number'] ?? null) : null,
            'itexia_id' => $validated['itexia_id'] ?? null,
            'created_by_user_id' => $request->user()?->getAuthIdentifier(),
            'invoice_number_pending' => false,
            'is_clarification' => false,
            'is_missing' => false,
        ]);

        $url = route('apps.assets.show', $asset->id);

        return Response::structured([
            'created' => true,
            'flow' => [
                'herkunft' => $validated['herkunft'],
                'wert' => $wert,
                'wertgrenze_itexia' => $wertgrenze,
                'itexia_required' => $isOverThreshold,
            ],
            'resolved' => [
                'asset_type_id' => $assetTypeId,
                'asset_vendor_id' => $assetVendorId,
                'user_id' => $userId,
                'owner_query' => $ownerQuery,
            ],
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
                'model' => $asset->model,
                'serial_number' => $asset->serial_number,
                'asset_type_id' => $asset->asset_type_id,
                'asset_vendor_id' => $asset->asset_vendor_id,
                'user_id' => $asset->user_id,
                'order_number' => $asset->order_number,
                'invoice_number' => $asset->invoice_number,
                'itexia_id' => $asset->itexia_id,
                'url' => $url,
                'url_markdown' => sprintf('[Asset #%d](%s)', $asset->id, $url),
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'herkunft' => $schema->string()
                ->enum(['bestellung', 'beschaffung'])
                ->description('Beschaffungsweg des Assets: "bestellung" (Intranet-Bestellprozess) oder "beschaffung" (Direktkauf ohne Bestellprozess).')
                ->required(),
            'wert' => $schema->number()
                ->description('Wert des Assets in Euro. Optional bei herkunft="bestellung" mit vorhandener order_number oder wenn itexia_id bereits gesetzt ist. Nur wenn wert über der Wertgrenze liegt, wird itexia_id verpflichtend.')
                ->nullable(),
            'model' => $schema->string()
                ->description('Modellbezeichnung des Assets.')
                ->required(),
            'asset_type_id' => $schema->integer()
                ->description('ID des Asset-Typs. Bevorzugt explizit; alternativ kann über asset_type/type/typ/kategorie aufgelöst werden.')
                ->nullable(),
            'asset_vendor_id' => $schema->integer()
                ->description('ID des Herstellers. Bevorzugt explizit; alternativ kann über vendor/hersteller aufgelöst werden.')
                ->nullable(),
            'asset_type' => $schema->string()
                ->description('Name des Asset-Typs als Alternative zu asset_type_id (Alias: type, typ, kategorie).')
                ->nullable(),
            'type' => $schema->string()
                ->description('Alias für asset_type.')
                ->nullable(),
            'typ' => $schema->string()
                ->description('Alias für asset_type.')
                ->nullable(),
            'kategorie' => $schema->string()
                ->description('Alias für asset_type.')
                ->nullable(),
            'vendor' => $schema->string()
                ->description('Herstellername als Alternative zu asset_vendor_id.')
                ->nullable(),
            'hersteller' => $schema->string()
                ->description('Alias für vendor.')
                ->nullable(),
            'serial_number' => $schema->string()
                ->description('Seriennummer.')
                ->required(),
            'order_number' => $schema->string()
                ->description('Bestellnummer (BEN). Primäres Pflichtfeld bei herkunft="bestellung" (Intranet-Bestellprozess).')
                ->nullable(),
            'invoice_number' => $schema->string()
                ->description('Rechnungsnummer. Pflicht bei herkunft="beschaffung".')
                ->nullable(),
            'itexia_id' => $schema->string()
                ->description('Itexia-ID. Pflicht nur dann, wenn ein gesetzter wert die Wertgrenze erreicht/überschreitet.')
                ->nullable(),
            'name' => $schema->string()
                ->description('Optionaler Gerätename.')
                ->nullable(),
            'location' => $schema->string()
                ->description('Standort. Pflicht, wenn keine user_id übergeben wird.')
                ->nullable(),
            'user_id' => $schema->integer()
                ->description('Besitzer-ID. Vorher über benutzer_suchen ermitteln.')
                ->nullable(),
            'owner' => $schema->string()
                ->description('Freitext für Besitzer-Suche (z. B. "kopec"). Wird zur user_id-Auflösung verwendet, falls keine user_id übergeben wird.')
                ->nullable(),
            'besitzer_query' => $schema->string()
                ->description('Alias für owner.')
                ->nullable(),
            'besitzer' => $schema->object([
                'name' => $schema->string()->description('Vollständiger Anzeigename des Besitzers.')->nullable(),
                'vorname' => $schema->string()->description('Vorname des Besitzers.')->nullable(),
                'nachname' => $schema->string()->description('Nachname des Besitzers.')->nullable(),
                'username' => $schema->string()->description('Username des Besitzers.')->nullable(),
                'email' => $schema->string()->description('E-Mail des Besitzers.')->nullable(),
            ])
                ->description('Besitzer-Metadaten zur Dokumentation. Für die Anlage ist weiterhin user_id maßgeblich.')
                ->nullable(),
        ];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'created' => $schema->boolean()
                ->description('Kennzeichnet, ob das Asset erfolgreich angelegt wurde.')
                ->required(),
            'flow' => $schema->object([
                'herkunft' => $schema->string()->description('Verarbeitete Herkunft.')->required(),
                'wert' => $schema->number()->description('Verarbeiteter Wert in Euro (kann null sein, wenn wert nicht übergeben wurde).')->nullable(),
                'wertgrenze_itexia' => $schema->number()->description('Aktive Wertgrenze aus den App-Einstellungen.')->required(),
                'itexia_required' => $schema->boolean()->description('Ob für diesen Datensatz anhand des übergebenen werts eine Itexia-ID erforderlich war (kann null sein, wenn wert fehlt).')->nullable(),
            ])
                ->description('Zusammenfassung der angewendeten Pflichtlogik.')
                ->required(),
            'asset' => $schema->object([
                'id' => $schema->integer()->description('ID des neu angelegten Assets.')->required(),
                'name' => $schema->string()->description('Name des Assets.')->nullable(),
                'model' => $schema->string()->description('Modell des Assets.')->required(),
                'serial_number' => $schema->string()->description('Seriennummer des Assets.')->required(),
                'asset_type_id' => $schema->integer()->description('Asset-Typ-ID.')->required(),
                'asset_vendor_id' => $schema->integer()->description('Hersteller-ID.')->required(),
                'user_id' => $schema->integer()->description('Besitzer-ID.')->nullable(),
                'order_number' => $schema->string()->description('Bestellnummer (BEN).')->nullable(),
                'invoice_number' => $schema->string()->description('Rechnungsnummer.')->nullable(),
                'itexia_id' => $schema->string()->description('Itexia-ID.')->nullable(),
                'url' => $schema->string()->description('Direkter Link zur Asset-Detailseite.')->required(),
                'url_markdown' => $schema->string()->description('Markdown-Link zur Asset-Detailseite.')->required(),
            ])
                ->description('Daten des neu angelegten Assets.')
                ->required(),
        ];
    }

    private function appSettings(): AppSettings
    {
        $settings = IntranetAppAssetsSettings::current()?->settings;

        return $settings ?? new AppSettings;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveAssetTypeId(array $validated): ?int
    {
        $id = isset($validated['asset_type_id']) ? (int) $validated['asset_type_id'] : null;
        if ($id !== null && $id > 0) {
            return $id;
        }

        $name = $validated['asset_type'] ?? $validated['type'] ?? $validated['typ'] ?? $validated['kategorie'] ?? null;
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($name));
        $exact = AssetType::query()->whereRaw('LOWER(name) = ?', [$normalized])->pluck('id');
        if ($exact->count() === 1) {
            return (int) $exact->first();
        }
        if ($exact->count() > 1) {
            return null;
        }

        $partial = AssetType::query()->whereRaw('LOWER(name) LIKE ?', ['%'.$normalized.'%'])->pluck('id');

        return $partial->count() === 1 ? (int) $partial->first() : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveAssetVendorId(array $validated): ?int
    {
        $id = isset($validated['asset_vendor_id']) ? (int) $validated['asset_vendor_id'] : null;
        if ($id !== null && $id > 0) {
            return $id;
        }

        $name = $validated['vendor'] ?? $validated['hersteller'] ?? null;
        if ((! is_string($name) || trim($name) === '') && isset($validated['model']) && is_string($validated['model'])) {
            $name = $this->inferVendorFromModel($validated['model']);
        }

        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($name));
        $exact = AssetVendor::query()->whereRaw('LOWER(name) = ?', [$normalized])->pluck('id');
        if ($exact->count() === 1) {
            return (int) $exact->first();
        }
        if ($exact->count() > 1) {
            return null;
        }

        $partial = AssetVendor::query()->whereRaw('LOWER(name) LIKE ?', ['%'.$normalized.'%'])->pluck('id');

        return $partial->count() === 1 ? (int) $partial->first() : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>|null  $besitzer
     */
    private function resolveOwnerQuery(array $validated, ?array $besitzer): ?string
    {
        foreach (['owner', 'besitzer_query'] as $field) {
            if (isset($validated[$field]) && is_string($validated[$field]) && trim($validated[$field]) !== '') {
                return trim($validated[$field]);
            }
        }

        if ($besitzer !== null) {
            foreach (['username', 'email', 'name', 'nachname', 'vorname'] as $field) {
                if (isset($besitzer[$field]) && is_string($besitzer[$field]) && trim($besitzer[$field]) !== '') {
                    return trim($besitzer[$field]);
                }
            }
        }

        return null;
    }

    private function resolveUniqueUserId(string $query): ?int
    {
        $normalized = mb_strtolower(trim($query));

        $exact = User::query()
            ->whereRaw('LOWER(username) = ?', [$normalized])
            ->orWhereRaw('LOWER(email) = ?', [$normalized])
            ->orWhereRaw('LOWER(nachname) = ?', [$normalized])
            ->orWhereRaw('LOWER(vorname) = ?', [$normalized])
            ->orWhereRaw("LOWER(CONCAT(vorname, ' ', nachname)) = ?", [$normalized])
            ->pluck('id');

        if ($exact->count() === 1) {
            return (int) $exact->first();
        }

        if ($exact->count() > 1) {
            return null;
        }

        $partial = User::query()
            ->whereRaw('LOWER(username) LIKE ?', ['%'.$normalized.'%'])
            ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$normalized.'%'])
            ->orWhereRaw('LOWER(nachname) LIKE ?', ['%'.$normalized.'%'])
            ->orWhereRaw('LOWER(vorname) LIKE ?', ['%'.$normalized.'%'])
            ->orWhereRaw("LOWER(CONCAT(vorname, ' ', nachname)) LIKE ?", ['%'.$normalized.'%'])
            ->pluck('id');

        return $partial->count() === 1 ? (int) $partial->first() : null;
    }

    private function inferVendorFromModel(string $model): ?string
    {
        $modelNormalized = mb_strtolower($model);
        $vendors = AssetVendor::query()->pluck('name');
        $matches = $vendors->filter(function (mixed $vendor) use ($modelNormalized): bool {
            $name = mb_strtolower((string) $vendor);

            return $name !== '' && str_contains($modelNormalized, $name);
        })->values();

        if ($matches->count() === 1) {
            return (string) $matches->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function errorWithLog(string $message, array $context): Response
    {
        Log::warning('asset_anlegen validation error', [
            'message' => $message,
            'context' => $context,
        ]);

        return Response::error($message);
    }
}
