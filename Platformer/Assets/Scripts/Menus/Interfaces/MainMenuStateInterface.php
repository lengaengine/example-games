<?php

namespace Lenga\Platformer\Scripts\Menus\Interfaces;

use Lenga\Platformer\Scripts\Menus\MainMenuContext;

/**
 *
 */
interface MainMenuStateInterface
{
    public function enter(MainMenuContext $context): void;

    public function exit(MainMenuContext $context): void;

    public function setState(MainMenuStateInterface $state): void;

    public function update(MainMenuContext $context): void;
}