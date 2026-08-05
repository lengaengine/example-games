<?php

namespace Lenga\Platformer\Scripts\Menus;

use Lenga\Engine\UI\Canvas;

final class MainMenuContext
{
    public function __construct(
        public ?Canvas $titleMenu = null,
        public ?Canvas $settingsMenu = null,
        public ?Canvas $audioSettingsMenu = null,
        public ?Canvas $controllerSettingsMenu = null,
        public ?Canvas $creditsScreen = null,
    )
    {
    }
}
