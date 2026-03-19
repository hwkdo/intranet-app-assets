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
    ) {}
}
