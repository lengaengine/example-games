<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Combat;

use Lenga\Engine\Attributes\AddComponentMenu;
use Lenga\Engine\Attributes\Tooltip;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\BoxCollider2D;
use Lenga\Engine\Core\Collision2D;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\Vector2;
use Lenga\Engine\Core\Vector3;

#[AddComponentMenu("Combat/Melee Hitbox")]
final class MeleeHitbox extends Behaviour
{
    #[Tooltip("Only GameObjects with this tag can be damaged by this hitbox.")]
    public string $targetTag = 'Enemy';

    #[Tooltip("Owning faction tag. Matching tags are ignored.")]
    public string $ownerTag = 'Player';

    #[Tooltip("Local horizontal offset from the owner pivot.")]
    public float $localOffsetX = 4.0;

    #[Tooltip("Local vertical offset from the owner pivot.")]
    public float $localOffsetY = 3.0;

    private ?BoxCollider2D $collider = null;
    private ?GameObject $owner = null;
    private bool $armed = false;
    private int $facingDirection = 1;
    private int $damage = 1;
    private float $knockbackX = 150.0;
    private float $knockbackY = -120.0;
    /** @var array<string, true> */
    private array $hitTargets = [];

    public function start(): void
    {
        $this->owner = $this->gameObject->getParent() ?? $this->gameObject;
        $this->collider = $this->gameObject->getComponent(BoxCollider2D::class);

        if ($this->collider instanceof BoxCollider2D) {
            $this->collider->isTrigger = true;
            $this->collider->enabled = false;
        }

        $this->syncFacingDirection(1);
    }

    public function syncFacingDirection(int $facingDirection): void
    {
        $this->facingDirection = $facingDirection >= 0 ? 1 : -1;
        $localPosition = $this->transform->localPosition;
        $this->transform->localPosition = new Vector3(
            abs($this->localOffsetX) * $this->facingDirection,
            $this->localOffsetY,
            $localPosition->z,
        );
    }

    public function beginSwing(int $damage, float $knockbackX, float $knockbackY, int $facingDirection): void
    {
        $this->damage = max(1, $damage);
        $this->knockbackX = abs($knockbackX);
        $this->knockbackY = $knockbackY;
        $this->hitTargets = [];
        $this->armed = true;
        $this->syncFacingDirection($facingDirection);

        if ($this->collider instanceof BoxCollider2D) {
            $this->collider->enabled = true;
        }
    }

    public function endSwing(): void
    {
        $this->armed = false;
        $this->hitTargets = [];

        if ($this->collider instanceof BoxCollider2D) {
            $this->collider->enabled = false;
        }
    }

    public function onTriggerEnter2D(Collision2D $collision): void
    {
        $this->tryHit($collision);
    }

    public function onTriggerStay2D(Collision2D $collision): void
    {
        $this->tryHit($collision);
    }

    private function tryHit(Collision2D $collision): void
    {
        if (!$this->armed) {
            return;
        }

        $other = $collision->otherGameObject;
        if (!$other instanceof GameObject) {
            return;
        }

        if ($other->compareTag($this->ownerTag)) {
            return;
        }

        if ($this->targetTag !== '' && !$other->compareTag($this->targetTag)) {
            return;
        }

        $targetKey = $other->sceneObjectId !== ''
            ? $other->sceneObjectId
            : ($other->getInstanceId() !== null ? (string) $other->getInstanceId() : $other->name);
        if (isset($this->hitTargets[$targetKey])) {
            return;
        }

        $damageable = $other->getComponent(Damageable::class);
        if (!$damageable instanceof Damageable) {
            return;
        }

        $applied = $damageable->applyHit(
            $this->damage,
            new Vector2($this->knockbackX * $this->facingDirection, $this->knockbackY),
            $this->owner,
        );

        if ($applied) {
            $this->hitTargets[$targetKey] = true;
        }
    }
}
