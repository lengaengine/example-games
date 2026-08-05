<?php

namespace Lenga\Platformer\Scripts\Menus\States;

use Lenga\Engine\UI\Canvas;
use Lenga\Platformer\Scripts\Menus\Interfaces\MainMenuStateInterface;
use Lenga\Platformer\Scripts\Menus\MainMenu;
use Lenga\Platformer\Scripts\Menus\MainMenuContext;

abstract class AbstractMainMenuState implements MainMenuStateInterface
{
    public function __construct(protected MainMenu $menu)
    {
    }

    public function enter(MainMenuContext $context): void
    {
        $canvas = $this->getNavigationCanvas($context);
        if ($canvas === null) {
            return;
        }

        $canvas->clearSelection();
        $firstSelected = $canvas->getFirstSelectedElement();
        if ($firstSelected !== null) {
            $canvas->selectElement($firstSelected);
        }
    }

    public function exit(MainMenuContext $context): void
    {
        $this->getNavigationCanvas($context)?->clearSelection();
    }

    public final function setState(MainMenuStateInterface $state): void
    {
        $this->menu->setState($state);
    }

    public function update(MainMenuContext $context): void
    {
    }

    protected function getNavigationCanvas(MainMenuContext $context): ?Canvas
    {
        return $context->titleMenu;
    }
}
