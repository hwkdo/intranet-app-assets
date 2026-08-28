<?php

namespace Hwkdo\IntranetAppAssets\Data;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\Attributes\HiddenFromSettings;
use Hwkdo\IntranetAppBase\Data\BaseUserSettings;

class UserSettings extends BaseUserSettings
{
    public function __construct(
        #[HiddenFromSettings]
        #[Description('Persönliches Dashboard-Layout (Widgets, Positionen, Größen)')]
        public array $dashboard = [
            'version' => 1,
            'enabledWidgets' => [],
            'layout' => [],
        ],
    ) {}
}
