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
            'all_users' => true,
        ],
    ],

    /*
     * Formwerk-URL für Übergabe-Bestätigung (optional). Wenn gesetzt, wird "Per Formwerk bestätigen"
     * angezeigt und verlinkt auf diese URL mit handover_id, asset_id und recipient_id als Query-Parameter.
     */
    'formwerk_handover_url' => env('INTRANET_APP_ASSETS_FORMWERK_HANDOVER_URL', ''),

    /*
     * Erwarteter Belegtyp-ZB in D3 für gültige Rechnungen (Property 82).
     * Nur Zahlungsbelege mit diesem Belegtyp werden als valide Rechnungsnummer akzeptiert.
     */
    'd3_invoice_belegtyp' => env('INTRANET_APP_ASSETS_D3_INVOICE_BELEGTYP', 'Rechnung'),

    /*
     * D3-Rechnungsanalyse via OCR/Vision:
     * - max_pages: Begrenzung für große Rechnungs-PDFs
     * - dpi: Raster-Auflösung für bessere OCR-Erkennung
     * - vision_model / vision_model_langdock: Env-Fallback, wenn in AppSettings (Admin) die Felder leer sind.
     * - vision_http_timeout: Sekunden für /chat/completions (Vision kann länger brauchen als 600 s)
     * - vision_connect_timeout: Sekunden bis zum TCP-Connect (schnelles Fail bei totem Host)
     */
    'd3_invoice_ocr_max_pages' => (int) env('INTRANET_APP_ASSETS_D3_INVOICE_OCR_MAX_PAGES', 12),
    'd3_invoice_ocr_dpi' => (int) env('INTRANET_APP_ASSETS_D3_INVOICE_OCR_DPI', 180),
    #'d3_invoice_vision_model' => env('INTRANET_APP_ASSETS_D3_INVOICE_VISION_MODEL', 'llama3.2-vision:11b'),
    'd3_invoice_vision_model' => env('INTRANET_APP_ASSETS_D3_INVOICE_VISION_MODEL', 'qwen2.5vl:7b'),

    /*
     * Modell für D3-Vision, wenn AppSettings → Langdock. Muss eine von Langdock erlaubte ID sein (siehe 400-Fehlertext).
     */
    'd3_invoice_vision_model_langdock' => env('INTRANET_APP_ASSETS_D3_INVOICE_VISION_MODEL_LANGDOCK', 'gpt-5-mini'),
    'd3_invoice_vision_http_timeout' => (int) env('INTRANET_APP_ASSETS_D3_INVOICE_VISION_HTTP_TIMEOUT', 1200),
    'd3_invoice_vision_connect_timeout' => (int) env('INTRANET_APP_ASSETS_D3_INVOICE_VISION_CONNECT_TIMEOUT', 30),

    /*
     * max_tokens für Langdock Chat Completions (D3-Rechnungsvision, OpenAI-kompatibel).
     */
    'd3_invoice_langdock_max_tokens' => (int) env('INTRANET_APP_ASSETS_D3_INVOICE_LANGDOCK_MAX_TOKENS', 8192),
    'd3_invoice_ocr_debug_log' => filter_var(env('INTRANET_APP_ASSETS_D3_INVOICE_OCR_DEBUG_LOG', 'true'), FILTER_VALIDATE_BOOLEAN),

    /*
     * Gecachte D3-Rechnungsanalyse (Vision): Auto-Queue bei invoice_number, Backfill-Command, MCP-Cache.
     */
    'd3_invoice_auto_analyze_enabled' => filter_var(env('INTRANET_APP_ASSETS_D3_INVOICE_AUTO_ANALYZE_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
    'd3_invoice_analysis_queue' => env('INTRANET_APP_ASSETS_D3_INVOICE_ANALYSIS_QUEUE', null),
    'd3_invoice_analysis_reanalyze_on_model_change' => filter_var(env('INTRANET_APP_ASSETS_D3_INVOICE_REANALYZE_ON_MODEL_CHANGE', 'false'), FILTER_VALIDATE_BOOLEAN),
];
