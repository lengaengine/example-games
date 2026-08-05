<?php

namespace Lenga\Platformer\Scripts\Enemy\States;

use Lenga\Platformer\Scripts\Enemy\Enemy;
use Lenga\Platformer\Scripts\Enemy\Interfaces\EnemyStateInterface;

abstract class AbstractEnemyState implements EnemyStateInterface
{
    public function __construct(
        protected Enemy $enemy
    )
    {
    }

    public function setState(EnemyStateInterface $state): void
    {
        $this->enemy->setState($state);
    }
}