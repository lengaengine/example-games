<?php

namespace Lenga\Platformer\Scripts\Menus\States;

use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\Input;
use Lenga\Engine\UI\Button;
use Lenga\Platformer\Scripts\Input\Enumerations\ButtonType;
use Lenga\Platformer\Scripts\Menus\Enumerations\MenuButtonType;
use Lenga\Platformer\Scripts\Menus\MainMenuContext;

class TitleMenuState extends AbstractMainMenuState
{
    public function enter(MainMenuContext $context): void
    {
        if ($context->titleMenu) {
            $context->titleMenu->enabled = true;
            $this->menu->animateCanvasIn($context->titleMenu);
        }

        parent::enter($context);
    }

    public function exit(MainMenuContext $context): void
    {
        parent::exit($context);

        if ($context->titleMenu) {
            $this->menu->restoreCanvasRestState($context->titleMenu);
            $context->titleMenu->enabled = false;
        }
    }

    public function update(MainMenuContext $context): void
    {
        parent::update($context);

        if (Input::getButtonDown(ButtonType::CONFIRM->value)) {
            $canvas = $context->titleMenu;
            switch ($canvas->getSelectedElement()->name) {
                case MenuButtonType::PLAY->value:
                    $this->menu->onPlayButtonClicked();
                    break;

                case MenuButtonType::SETTINGS->value:
                    $this->menu->onSettingsButtonClicked();
                    break;

                case MenuButtonType::QUIT->value:
                    $this->menu->onQuitButtonClicked();
                    break;
            }
        }
    }
}
