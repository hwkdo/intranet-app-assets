<?php

declare(strict_types=1);

use Prism\Relay\Enums\Transport;

return [
    'servers' => [
        'assets' => [
            'transport' => Transport::Http,
            'url' => env('RELAY_ASSETS_SERVER_URL', 'http://localhost/mcp/apps/assets'),
            'timeout' => env('RELAY_ASSETS_SERVER_TIMEOUT', 30),
            'headers' => [
                // Bearer Token wird dynamisch zur Laufzeit hinzugefügt.
            ],
        ],
    ],
];
