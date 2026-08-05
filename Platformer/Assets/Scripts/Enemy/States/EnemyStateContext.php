<?php

namespace Lenga\Platformer\Scripts\Enemy\States;

use Lenga\Engine\Core\SpriteAnimation;
use Lenga\Engine\Core\Transform;
use Lenga\Platformer\Scripts\Enemy\Enemy;
use Lenga\Platformer\Scripts\Enemy\Interfaces\EnemyStateContextInterface;

readonly class EnemyStateContext implements EnemyStateContextInterface
{
    public function __construct(
        public Enemy $enemy,
        public ?SpriteAnimation $animation = null,
        public ?Transform $target = null,
    )
    {
    }
}