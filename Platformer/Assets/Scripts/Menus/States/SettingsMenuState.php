<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Menus\States;

use Lenga\Engine\Core\Input;
use Lenga\Engine\UI\Canvas;
use Lenga\Platformer\Scripts\Input\Enumerations\ButtonType;
use Lenga\Platformer\Scripts\Menus\Enumerations\MenuButtonType;
use Lenga\Platformer\Scripts\Menus\MainMenuContext;
use Override;

class SettingsMenuState extends AbstractMainMenuState
{
    public function enter(MainMenuContext $context): void
    {
        if ($context->settingsMenu) {
            $context->settingsMenu->enabled = true;
            $this->menu->animateCanvasIn($context->settingsMenu);
        }

        parent::enter($context);
    }

    public function exit(MainMenuContext $context): void
    {
        parent::exit($context);

        if ($context->settingsMenu) {
            $this->menu->restoreCanvasRestState($context->settingsMenu);
            $context->settingsMenu->enabled = false;
        }
    }

    protected function getNavigationCanvas(MainMenuContext $context): ?Canvas
    {
        return $context->settingsMenu;
    }

    #[Override]
    public function update(MainMenuContext $context): void
    {
        parent::update($context);

        if (Input::getButtonDown(ButtonType::CANCEL->value)) {
            $this->setState(new TitleMenuState($this->menu));
            return;
        }

        if (Input::getButtonDown(ButtonType::CONFIRM->value)) {
            $selectedElement = $context->settingsMenu?->getSelectedElement();
            switch ($selectedElement?->name) {
                case MenuButtonType::AUDIO_SETTINGS->value:
                    $this->menu->onAudioSettingsButtonClicked();
                    break;

                case MenuButtonType::CONTROLLER_SETTINGS->value:
                    $this->menu->onControllerSettingsButtonClicked();
                    break;

                case MenuButtonType::CREDITS->value:
                    $this->menu->onCreditsButtonClicked();
                    break;
            }
        }
    }
}
