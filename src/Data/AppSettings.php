<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Data;

use Hwkdo\IntranetAppAssets\Enums\BenPruefungsQuelle;
use Hwkdo\IntranetAppAssets\Enums\D3InvoiceVisionLlmProvider;
use Hwkdo\IntranetAppBase\Contracts\HasAiSettings;
use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseAppSettings;
use Hwkdo\IntranetAppBase\Enums\AiProvider;

class AppSettings extends BaseAppSettings implements HasAiSettings
{
    public function __construct(
        #[Description('Aktiviert die Beispiel-Funktionalität')]
        public bool $enableExampleFeature = true,

        #[Description('Assets mit Rechnungsnr. dürfen in Itexia/Seventhings angelegt werden')]
        public bool $allowCreateInItexiaWithInvoiceNumber = false,

        #[Description('Maximale Anzahl von Elementen pro Seite')]
        public int $maxItemsPerPage = 25,

        #[Description('Standard-Theme für die App')]
        public string $defaultTheme = 'light',

        #[Description('Liste der erlaubten Bereiche')]
        public array $allowedAreas = ['public', 'private'],

        #[Description('Wertgrenze in Euro (z. B. 250) für Assistenten-Logik und Itexia-Pflichtfelder')]
        public int $wertgrenzeItexia = 250,

        #[Description('Bestellnummer (BEN) bei „Aus Bestellung“ auch bei Wert unter der Wertgrenze Pflicht')]
        public bool $benBenoetigtWennWertKleinerGrenze = true,

        #[Description('Basis-URL für DMS/D3-Links (Rechnung/Bestellung). Leer = Fallback auf d3-rest-laravel (dms-search-url). Nur setzen zum Überschreiben.')]
        public string $dmsBaseUrl = '',

        #[Description('LDAP-Attribut für die Itexia-ID am Computer-Objekt (z. B. msDS-cloudExtensionAttribute10)')]
        public string $ldapItexiaIdAttribute = 'msDS-cloudExtensionAttribute10',

        #[Description('OU-DNs pro Connection für die AD-Computer-Suche beim Domain-Abgleich. JSON: {"default":["OU=...,DC=..."],"schulung":[]}')]
        public array $computerSearchOus = ['default' => [], 'schulung' => []],

        #[Description('NetBIOS-Ressourcendomänen für den SCCM-Abgleich (v_R_System.Resource_Domain_OR_Workgr0) pro LDAP-Connection. Schlüssel: default, schulung')]
        public array $sccmResourceDomains = [
            'default' => 'HWKDO',
            'schulung' => 'HWK-SCHULUNG',
        ],

        #[Description('Kommagetrennte E-Mail-Adressen für Inventar-Hinweise, wenn ein Asset mit Itexia-ID gelöscht und in Seventhings gefunden wurde')]
        public string $empfaengerInventarMails = 'asset@hwk-do.de',

        #[Description('Tage ab Asset-Anlage: automatische D3-Suche nach Rechnung anhand BEN; danach als „fehlende Rechnungsnr.“ markieren (1–365)')]
        public int $invoiceAutoResolveMaxDays = 14,

        #[Description('OpenWebUi-Modell für KI Chat')]
        public string $openWebUiModel = 'intranet-app-assets',

        #[Description('OpenWebUI Collection-IDs für file_search im Assets-KI-Chat')]
        public array $openWebUiCollectionIds = [],

        #[Description('LLM-Backend für D3-Rechnungsvision (OCR + strukturierte Auswertung)')]
        public D3InvoiceVisionLlmProvider $d3InvoiceVisionLlmProvider = D3InvoiceVisionLlmProvider::OpenWebUi,

        #[Description('Vision-Modell für D3-Rechnungen bei Open Web UI (z. B. Ollama-Name). Leer = Fallback auf INTRANET_APP_ASSETS_D3_INVOICE_VISION_MODEL / OPENWEBUI_DEFAULT_MODEL.')]
        public string $d3InvoiceVisionModelOpenWebUi = '',

        #[Description('Vision-Modell für D3-Rechnungen bei Langdock (nur von Langdock erlaubte IDs, z. B. gpt-5-mini). Leer = Fallback auf INTRANET_APP_ASSETS_D3_INVOICE_VISION_MODEL_LANGDOCK.')]
        public string $d3InvoiceVisionModelLangdock = '',

        #[Description('KI-Text-Provider überschreiben (leer = Intranet-Base-Default)')]
        public ?AiProvider $aiTextProviderOverride = null,

        #[Description('KI-Text-Modell überschreiben (leer = Base- bzw. Provider-Default)')]
        public ?string $aiTextModelOverride = null,

        #[Description('KI-Bild-Provider überschreiben (leer = Intranet-Base-Default)')]
        public ?AiProvider $aiImageProviderOverride = null,

        #[Description('KI-Bild-Modell überschreiben (leer = Base- bzw. Provider-Default)')]
        public ?string $aiImageModelOverride = null,

        #[Description('Quelle für die BEN-Existenzprüfung: legacy = Legacy-Intranet, intranet_v3 = lokale Bestellungen (Intranet V3), beides = beide Systeme (BEN muss in mind. einem existieren)')]
        public BenPruefungsQuelle $benPruefungsQuelle = BenPruefungsQuelle::Legacy,
    ) {}

    public function textProviderOverride(): ?AiProvider
    {
        return $this->aiTextProviderOverride;
    }

    public function textModelOverride(): ?string
    {
        return $this->normalizedOverrideString($this->aiTextModelOverride);
    }

    public function imageProviderOverride(): ?AiProvider
    {
        return $this->aiImageProviderOverride;
    }

    public function imageModelOverride(): ?string
    {
        return $this->normalizedOverrideString($this->aiImageModelOverride);
    }

    private function normalizedOverrideString(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
