<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Physics\Effectors;

use Lenga\Engine\Attributes\AddComponentMenu;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Attributes\Tooltip;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\BuoyancyEffector2D;
use Lenga\Engine\Core\Collision2D;
use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\Input;
use Lenga\Engine\Core\MathUtil;
use Lenga\Engine\Core\Rigidbody2D;
use Lenga\Engine\Core\SpriteRenderer;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Vector3;

#[AddComponentMenu("Physics/Effectors/EffectorProbeController")]
final class EffectorProbeController extends Behaviour
{
    #[Tooltip("Horizontal movement speed for the effector playground probe.")]
    #[Range(5.0, 60.0)]
    public float $speed = 26.0;

    #[Tooltip("How quickly the probe reaches its target horizontal speed.")]
    #[Range(20.0, 400.0)]
    public float $acceleration = 180.0;

    #[Tooltip("The upward launch velocity used when jumping.")]
    #[Range(40.0, 260.0)]
    public float $jumpVelocity = 150.0;

    #[Tooltip("The upward push applied when jumping inside a buoyancy zone.")]
    #[Range(20.0, 220.0)]
    public float $swimStrokeVelocity = 95.0;

    #[Tooltip("Input values smaller than this are treated as zero.")]
    #[Range(0.0, 0.5)]
    public float $deadZone = 0.1;

    #[Tooltip("If the probe falls below this world Y position it respawns automatically.")]
    public float $respawnY = 72.0;

    protected ?Rigidbody2D $body = null;
    protected ?SpriteRenderer $renderer = null;
    protected ?Vector3 $spawnPosition = null;
    protected float $moveInput = 0.0;
    protected float $jumpBufferedUntil = -1.0;
    protected float $swimBufferedUntil = -1.0;
    protected float $lastBuoyancyTouchAt = -1000.0;

    public function start(): void
    {
        $this->body = $this->gameObject->getComponent(Rigidbody2D::class);
        $this->renderer = $this->gameObject->getComponent(SpriteRenderer::class);

        if ($this->body === null) {
            Debug::warn('EffectorProbeController requires a Rigidbody2D component.');
            return;
        }

        $position = $this->transform->position;
        $this->spawnPosition = new Vector3($position->x, $position->y, $position->z);
    }

    public function update(): void
    {
        $this->moveInput = Input::getAxis('Horizontal');
        if (MathUtil::abs($this->moveInput) <= $this->deadZone) {
            $this->moveInput = 0.0;
        }

        if ($this->renderer !== null && $this->moveInput !== 0.0) {
            $this->renderer->flipX = $this->moveInput < 0.0;
        }

        if (Input::getButtonDown('Jump')) {
            $this->jumpBufferedUntil = Time::time() + 0.12;
            $this->swimBufferedUntil = Time::time() + 0.12;
        }

        if (Input::getButtonDown('Cancel')) {
            $this->respawn();
        }

        if ($this->transform->position->y > $this->respawnY) {
            $this->respawn();
        }
    }

    public function fixedUpdate(): void
    {
        if ($this->body === null) {
            return;
        }

        $velocity = $this->body->velocity;
        $targetSpeed = $this->moveInput * $this->speed;
        $velocity->x = MathUtil::moveTowards(
            $velocity->x,
            $targetSpeed,
            $this->acceleration * Time::fixedDeltaTime()
        );

        if ($this->isInsideBuoyancy() && $this->swimBufferedUntil >= Time::time()) {
            $velocity->y = \min($velocity->y, -$this->swimStrokeVelocity);
            $this->swimBufferedUntil = -1.0;
            $this->jumpBufferedUntil = -1.0;
        } elseif ($this->jumpBufferedUntil >= Time::time() && $this->body->isGrounded()) {
            $velocity->y = -$this->jumpVelocity;
            $this->jumpBufferedUntil = -1.0;
            $this->swimBufferedUntil = -1.0;
        }

        $this->body->velocity = $velocity;
    }

    public function onTriggerEnter2D(Collision2D $collision): void
    {
        $this->refreshBuoyancyContact($collision);
    }

    public function onTriggerStay2D(Collision2D $collision): void
    {
        $this->refreshBuoyancyContact($collision);
    }

    private function respawn(): void
    {
        if ($this->body === null || $this->spawnPosition === null) {
            return;
        }

        $this->transform->position = new Vector3(
            $this->spawnPosition->x,
            $this->spawnPosition->y,
            $this->spawnPosition->z,
        );
        $this->body->velocity = Vector3::zero();
        $this->jumpBufferedUntil = -1.0;
        $this->swimBufferedUntil = -1.0;
        $this->lastBuoyancyTouchAt = -1000.0;
    }

    private function refreshBuoyancyContact(Collision2D $collision): void
    {
        $otherGameObject = $collision->otherGameObject;
        if ($otherGameObject === null) {
            return;
        }

        if ($otherGameObject->getComponent(BuoyancyEffector2D::class) instanceof BuoyancyEffector2D) {
            $this->lastBuoyancyTouchAt = Time::time();
        }
    }

    private function isInsideBuoyancy(): bool
    {
        return Time::time() <= $this->lastBuoyancyTouchAt + 0.15;
    }
}
