<?php

use Hwkdo\IntranetAppAssets\Mcp\Servers\AssetsServer;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetAnlegenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetSuchenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\AssetTypenAuflistenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\BenutzerSuchenTool;
use Hwkdo\IntranetAppAssets\Mcp\Tools\HerstellerAuflistenTool;

it('registriert die neuen MCP tools in der richtigen reihenfolge', function () {
    $server = new AssetsServer;
    $reflection = new ReflectionClass($server);
    $toolsProperty = $reflection->getProperty('tools');
    $toolsProperty->setAccessible(true);

    expect($toolsProperty->getValue($server))->toBe([
        BenutzerSuchenTool::class,
        HerstellerAuflistenTool::class,
        AssetTypenAuflistenTool::class,
        AssetSuchenTool::class,
        AssetAnlegenTool::class,
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
        ->toContain('Intranet-Bestellprozess');
});
