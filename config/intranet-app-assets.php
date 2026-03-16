<?php

return [
    /*
     * Feldname im Seventhings-API-Response für die Objekt-UUID (PATCH object/{objectUuid}).
     * Standard ist "uuid". Nur setzen, wenn eure Instanz ein anderes Feld nutzt.
     */
    'seventhings_object_id_key' => env('INTRANET_APP_ASSETS_SEVENTHINGS_OBJECT_ID_KEY', ''),

    /*
     * Domain-Connection-Werte für Assets mit Domain-Typ (is_domain_object).
     * Entsprechende LDAP-Connections (z. B. "default", "schulung") müssen in config/ldap.php definiert sein.
     */
    'domain_connections' => [
        'default' => 'Verwaltung',
        'schulung' => 'Schulung',
    ],

    'roles' => [
        'admin' => [
            'name' => 'App-Assets-Admin',
            'permissions' => ['see-app-assets', 'manage-app-assets'],
        ],
        'user' => [
            'name' => 'App-Assets-Benutzer',
            'permissions' => ['see-app-assets'],
        ],
    ],

    /*
     * Formwerk-URL für Übergabe-Bestätigung (optional). Wenn gesetzt, wird "Per Formwerk bestätigen"
     * angezeigt und verlinkt auf diese URL mit handover_id, asset_id und recipient_id als Query-Parameter.
     */
    'formwerk_handover_url' => env('INTRANET_APP_ASSETS_FORMWERK_HANDOVER_URL', ''),
];
