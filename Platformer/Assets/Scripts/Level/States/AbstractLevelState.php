<?php

namespace Lenga\Platformer\Scripts\Level\States;

use Lenga\Platformer\Scripts\Level\Interfaces\LevelStateInterface;
use Lenga\Platformer\Scripts\Level\LevelManager;
use Lenga\Platformer\Scripts\Level\LevelStateContext;

abstract class AbstractLevelState implements LevelStateInterface
{
    public function __construct(protected LevelManager $levelManager)
    {
    }

    public function setState(LevelStateInterface $state): void
    {
        $this->levelManager->setState($state);
    }

    public function update(LevelStateContext $context): void
    {
        // Do nothing... override this in the child class if you want to do something every frame
    }
}