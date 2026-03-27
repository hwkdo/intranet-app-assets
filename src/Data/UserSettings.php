<?php

namespace Hwkdo\IntranetAppAssets\Data;

use Hwkdo\IntranetAppAssets\Enums\ViewModeEnum;
use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseUserSettings;

class UserSettings extends BaseUserSettings
{
    public function __construct(
        #[Description('Standard-Anzeigemodus für die App')]
        public ViewModeEnum $defaultViewMode = ViewModeEnum::Grid,

        #[Description('Favoriten-Bereiche des Benutzers')]
        public array $favoriteAreas = [],

        #[Description('Benachrichtigungen aktiviert')]
        public bool $notificationsEnabled = true,

        #[Description('Persönliches Dashboard-Layout (Widgets, Positionen, Größen)')]
        public array $dashboard = [
            'version' => 1,
            'enabledWidgets' => [],
            'layout' => [],
        ],
    ) {}
}
