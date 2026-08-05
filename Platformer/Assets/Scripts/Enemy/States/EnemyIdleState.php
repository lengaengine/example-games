<?php

namespace Lenga\Platformer\Scripts\Enemy\States;

use Lenga\Platformer\Scripts\Enemy\Interfaces\EnemyStateContextInterface;
use Lenga\Platformer\Scripts\Enemy\States\AbstractEnemyState;

class EnemyIdleState extends AbstractEnemyState
{
    public function enter(EnemyStateContextInterface $context): void
    {
    }

    public function exit(EnemyStateContextInterface $context): void
    {
        // TODO: Implement exit() method.
    }

    public function update(EnemyStateContextInterface $context): void
    {
        // TODO: Implement update() method.
    }
}