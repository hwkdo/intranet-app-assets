<?php

use Hwkdo\IntranetAppAssets\Mcp\Servers\AssetsServer;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetAbfragenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetAnlegenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetSuchenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetTypenAuflistenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\BestellungPruefenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\D3RechnungAnalysierenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\HerstellerAuflistenTool;
use Hwkdo\IntranetAppBase\Mcp\Tools\BenutzerSuchenTool;
use Hwkdo\IntranetAppBase\Mcp\Tools\D3RechnungSuchenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\ItexiaRaumIntranetPruefenTool;
use Hwkdo\SeventhingsLaravel\Mcp\Tools\ItexiaRaumAktualisierenTool;

it('registriert die MCP tools in der erwarteten Reihenfolge', function () {
    $server = new AssetsServer;
    $reflection = new ReflectionClass($server);
    $toolsProperty = $reflection->getProperty('tools');
    $toolsProperty->setAccessible(true);

    expect($toolsProperty->getValue($server))->toBe([
        BenutzerSuchenTool::class,
        HerstellerAuflistenTool::class,
        AssetTypenAuflistenTool::class,
        AssetSuchenTool::class,
        AssetAbfragenTool::class,
        BestellungPruefenTool::class,
        D3RechnungAnalysierenTool::class,
        D3RechnungSuchenTool::class,
        AssetAnlegenTool::class,
        ItexiaRaumIntranetPruefenTool::class,
        ItexiaRaumAktualisierenTool::class,
    ]);
});

it('hat klare server instructions fuer den modell flow', function () {
    $server = new AssetsServer;
    $reflection = new ReflectionClass($server);
    $instructionsProperty = $reflection->getProperty('instructions');
    $instructionsProperty->setAccessible(true);
    $instructions = $instructionsProperty->getValue($server);

    expect($instructions)
        ->toContain('benutzer_suchen')
        ->toContain('hersteller_auflisten')
        ->toContain('asset_typen_auflisten')
        ->toContain('asset_anlegen')
        ->toContain('Intranet-Bestellprozess')
        ->toContain('d3_rechnung_analysieren')
        ->toContain('sofort d3_rechnung_analysieren(id=invoice_number)')
        ->toContain('itexia_actual_room_missing')
        ->toContain('nicht der Itexia-Ist-Raum')
        ->toContain('Keine Itexia-Raumzuordnung')
        ->toContain('itexia_raum_intranet_pruefen')
        ->toContain('itexia_raum_aktualisieren')
        ->toContain('itexia_rooms_synced_at');
});
