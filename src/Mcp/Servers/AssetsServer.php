<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Servers;

use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetAnlegenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetAbfragenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetTypenAuflistenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetSuchenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\BenutzerSuchenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\BestellungPruefenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\D3RechnungAbrufenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\D3RechnungSuchenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\HerstellerAuflistenTool;
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
    protected string $instructions = 'Dieser Server verwaltet IT-Assets. Volltextsuche: assets_suchen. Strukturierte Filter (z. B. alle Assets eines Besitzers): assets_abfragen. Workflow zur Anlage: 1) benutzer_suchen für user_id, 2) hersteller_auflisten für asset_vendor_id, 3) asset_typen_auflisten für asset_type_id, 4) herkunftsspezifische Prüfung durchführen, 5) asset_anlegen. Bei herkunft="bestellung" zuerst bestellung_pruefen verwenden und dann mit gültiger order_number/BEN anlegen. Bei herkunft="beschaffung" zuerst d3_rechnung_suchen nutzen, optional d3_rechnung_abrufen für Metadaten/PDF-Analyse, und mit gültiger invoice_number anlegen. Bei Wert über der Wertgrenze ist itexia_id verpflichtend.';

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
