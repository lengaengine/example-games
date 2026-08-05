<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Combat;

use Lenga\Engine\Attributes\AddComponentMenu;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Attributes\Tooltip;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Collision2D;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\MathUtil;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Vector2;

#[AddComponentMenu("Combat/Enemy Touch Damage")]
final class EnemyTouchDamage extends Behaviour
{
    public string $targetTag = 'Player';

    #[Range(1, 5)]
    public int $damage = 1;

    #[Tooltip("Cooldown between touch hits.")]
    #[Range(0.0, 2.0)]
    public float $cooldown = 0.75;

    #[Tooltip("Horizontal knockback speed applied away from the enemy.")]
    #[Range(0.0, 400.0)]
    public float $knockbackX = 140.0;

    #[Tooltip("Vertical knockback speed applied on contact. Negative values launch upward.")]
    #[Range(-400.0, 400.0)]
    public float $knockbackY = -220.0;

    private float $nextDamageAt = -1.0;

    public function onCollisionEnter2D(Collision2D $collision): void
    {
        $this->tryDamage($collision);
    }

    public function onCollisionStay2D(Collision2D $collision): void
    {
        $this->tryDamage($collision);
    }

    private function tryDamage(Collision2D $collision): void
    {
        if (Time::time() < $this->nextDamageAt) {
            return;
        }

        $other = $collision->otherGameObject;
        if (!$other instanceof GameObject || !$other->compareTag($this->targetTag)) {
            return;
        }

        $damageable = $other->getComponent(Damageable::class);
        if (!$damageable instanceof Damageable) {
            return;
        }

        $direction = MathUtil::sign($other->transform->position->x - $this->transform->position->x);
        if ($direction === 0.0) {
            $direction = 1.0;
        }

        $applied = $damageable->applyHit(
            $this->damage,
            new Vector2($this->knockbackX * $direction, $this->knockbackY),
            $this->gameObject,
        );

        if ($applied) {
            $this->nextDamageAt = Time::time() + $this->cooldown;
        }
    }
}
