<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Servers;

use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetAnlegenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetAbfragenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetTypenAuflistenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetSuchenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\BestellungPruefenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\HerstellerAuflistenTool;
use Hwkdo\IntranetAppBase\Mcp\Tools\BenutzerSuchenTool;
use Hwkdo\IntranetAppBase\Mcp\Tools\D3RechnungAbrufenTool;
use Hwkdo\IntranetAppBase\Mcp\Tools\D3RechnungSuchenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\ItexiaRaumIntranetPruefenTool;
use Hwkdo\SeventhingsLaravel\Mcp\Tools\ItexiaRaumAktualisierenTool;
use Laravel\Mcp\Server;

class AssetsServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Assets Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '1.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = 'Dieser Server verwaltet IT-Assets. Volltextsuche: assets_suchen. Strukturierte Filter: assets_abfragen. Treffer enthalten gecachte Seventhings-Raum-IDs: itexia_actual_room_id, itexia_target_room_id, itexia_rooms_synced_at (Hintergrund-Sync; können veraltet sein bis zum nächsten Sync). Wenn der Nutzer «in Itexia gefunden», «mit Itexia-UUID» sagt: assets_abfragen mit found_in_itexia=true (itexia_uuid gesetzt). Nur itexia_id ohne UUID ist nicht «gefunden». «Keine Itexia-Raumzuordnung» / «ohne Ist-Raum laut Sync»: assets_abfragen mit found_in_itexia=true und itexia_actual_room_missing=true (Spalte itexia_actual_room_id ist null). Optional zusätzlich itexia_target_room_missing=true für fehlenden Soll-Raum. Das Antwortfeld «location» ist nur der Intranet-Standorttext — nicht der Itexia-Ist-Raum; niemals nur location=null als Ersatz für Itexia-Raumfilter nutzen. Live-Abgleich Itexia ↔ Intranet weiter mit itexia_raum_intranet_pruefen / itexia_raum_aktualisieren. Workflow zur Anlage: 1) benutzer_suchen für user_id, 2) hersteller_auflisten für asset_vendor_id, 3) asset_typen_auflisten für asset_type_id, 4) herkunftsspezifische Prüfung durchführen, 5) asset_anlegen. Bei herkunft="bestellung" zuerst bestellung_pruefen verwenden und dann mit gültiger order_number/BEN anlegen. Bei herkunft="beschaffung" zuerst d3_rechnung_suchen nutzen, optional d3_rechnung_abrufen für Metadaten/PDF-Analyse, und mit gültiger invoice_number anlegen. Bei Wert über der Wertgrenze ist itexia_id verpflichtend. Intranet-Bestellprozess: wie in der App-Dokumentation. Itexia/Seventhings Ist-Raum (Abgleich Intranet ↔ Itexia): itexia_raum_intranet_pruefen mit barcode=itexia_id: Ziel aus Besitzer-Raum (owner.raum), sonst Standort (location). Eindeutiger Treffer: expected_actual_room_id, should_update. Mehrere Treffer: expected_ambiguous true, matching_rooms zur Auswahl; Nutzer wählt id, dann itexia_raum_aktualisieren mit actual_room_id (manage-app-assets) und object_uuid oder barcode. Raum setzen: immer den Tool-Call itexia_raum_aktualisieren verwenden — keine JSON-Payloads oder Hinweise auf manuelle API-Aufrufe; wenn der Nutzer eine Aktualisierung will (oder ausdrücklich nur prüfen: nur Ergebnis, kein itexia_raum_aktualisieren).';

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        BenutzerSuchenTool::class,
        HerstellerAuflistenTool::class,
        AssetTypenAuflistenTool::class,
        AssetSuchenTool::class,
        AssetAbfragenTool::class,
        BestellungPruefenTool::class,
        D3RechnungSuchenTool::class,
        D3RechnungAbrufenTool::class,
        AssetAnlegenTool::class,
        ItexiaRaumIntranetPruefenTool::class,
        ItexiaRaumAktualisierenTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
