<?php

use Hwkdo\IntranetAppAssets\Mcp\Servers\AssetsServer;
use Hwkdo\IntranetAppAssets\Middleware\LogMcpAssetsHeaders;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/apps/assets', AssetsServer::class)
    ->middleware([
        LogMcpAssetsHeaders::class,
        'auth:api',
    ]);
