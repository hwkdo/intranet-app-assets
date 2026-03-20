<?php

namespace Hwkdo\IntranetAppAssets\Data;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseAppSettings;

class AppSettings extends BaseAppSettings
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

        #[Description('Kommagetrennte E-Mail-Adressen für Inventar-Hinweise, wenn ein Asset mit Itexia-ID gelöscht und in Seventhings gefunden wurde')]
        public string $empfaengerInventarMails = 'asset@hwk-do.de',
    ) {}
}
