<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Enemy;

use Lenga\Engine\Attributes\AddComponentMenu;
use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\SpriteAnimation;
use Lenga\Engine\Core\Vector3;
use Lenga\Platformer\Scripts\Enemy\Enumerations\EnemyState;
use Lenga\Platformer\Scripts\Enemy\Interfaces\EnemyStateInterface;
use Lenga\Platformer\Scripts\Enemy\States\EnemyStateContext;
use Lenga\Platformer\Scripts\Enemy\States\EnemyIdleState;

#[AddComponentMenu("Enemy/Enemy")]
final class Enemy extends Behaviour
{
    public EnemyState $state = EnemyState::Idle;

    #[Header("Movement Settings")]
    public ?Vector3 $startPosition = null;

    #[Header("Patrol Settings")]
    public float $patrolWalkSpeed = 5.0;
    public ?Vector3 $minPatrolPosition = null;
    public ?Vector3 $maxPatrolPosition = null;

    protected ?EnemyStateInterface $_state = null;
    protected ?EnemyStateContext $context = null;

    public function start(): void
    {
        $this->context = new EnemyStateContext(
            $this,
            $this->gameObject->getComponent(SpriteAnimation::class),
        );

        $this->setState(new EnemyIdleState($this));
    }

    public function update(): void
    {
        $this->_state->update($this->context);
    }

    public function setState(EnemyStateInterface $_state): void
    {
        $this->_state?->exit($this->context);
        $this->_state = $_state;
        $this->_state->enter($this->context);
    }
}
