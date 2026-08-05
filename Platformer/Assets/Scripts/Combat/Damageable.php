<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Combat;

use Lenga\Engine\Attributes\AddComponentMenu;
use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Attributes\Tooltip;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\ParticleSystem;
use Lenga\Engine\Core\Rigidbody2D;
use Lenga\Engine\Core\SpriteAnimation;
use Lenga\Engine\Core\SpriteRenderer;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Vector2;
use Lenga\Engine\Core\Vector3;

#[AddComponentMenu("Combat/Damageable")]
final class Damageable extends Behaviour
{
    #[Header("Health")]
    #[Range(1, 20)]
    public int $maxHealth = 3;

    #[Tooltip("Brief invulnerability window after taking a hit.")]
    #[Range(0.0, 2.0)]
    public float $invulnerabilityDuration = 0.2;

    #[Tooltip("Optional animation state to play when damaged but still alive.")]
    public string $hurtState = '';

    #[Tooltip("Optional animation trigger to fire when damaged but still alive.")]
    public string $hurtTrigger = '';

    #[Tooltip("Optional animation state to play when health reaches zero.")]
    public string $deathState = '';

    #[Tooltip("Disable the GameObject when health reaches zero.")]
    public bool $disableGameObjectOnDeath = true;

    #[Tooltip("Destroy the GameObject when health reaches zero.")]
    public bool $destroyOnDeath = false;

    #[Tooltip("Flash the sprite when taking damage.")]
    public bool $enableDamageFlash = true;

    #[Tooltip("Optional child particle emitter used for local hit impact feedback.")]
    public string $hitImpactEmitterPath = 'Hit Impact FX';

    #[Range(0, 255)]
    public int $flashRed = 255;

    #[Range(0, 255)]
    public int $flashGreen = 96;

    #[Range(0, 255)]
    public int $flashBlue = 96;

    #[Range(0.0, 60.0)]
    public float $flashPulseSpeed = 28.0;

    private(set) int $currentHealth = 0 {
        get {
            return $this->currentHealth;
        }
    }
    private float $invulnerableUntil = -1.0;
    private bool $dead = false;
    private ?Rigidbody2D $body = null;
    private ?SpriteAnimation $animation = null;
    private ?SpriteRenderer $renderer = null;
    private ?ParticleSystem $hitImpactFx = null;
    private bool $flashResetPending = false;
    private bool $hitImpactEmitterLookupFailed = false;

    public function start(): void
    {
        $this->currentHealth = max(1, $this->maxHealth);
        $this->body = $this->gameObject->getComponent(Rigidbody2D::class);
        $this->animation = $this->gameObject->getComponent(SpriteAnimation::class);
        $this->renderer = $this->gameObject->getComponent(SpriteRenderer::class);
    }

    public function update(): void
    {
        if (!$this->renderer instanceof SpriteRenderer || !$this->enableDamageFlash) {
            return;
        }

        $now = Time::unscaledTime();
        if (!$this->dead && $now < $this->invulnerableUntil) {
            $pulse = 0.5 + (sin($now * $this->flashPulseSpeed) * 0.5);
            $this->renderer->setColor(
                (int) round(255 + (($this->flashRed - 255) * $pulse)),
                (int) round(255 + (($this->flashGreen - 255) * $pulse)),
                (int) round(255 + (($this->flashBlue - 255) * $pulse)),
            );
            $this->flashResetPending = true;
            return;
        }

        if ($this->flashResetPending) {
            $this->renderer->setColor(255, 255, 255);
            $this->flashResetPending = false;
        }
    }

    public bool $isDead {
        get {
            return $this->dead;
        }
    }

    public function applyHit(int $damage, Vector2 $knockback, ?GameObject $attacker = null): bool
    {
        if ($this->dead || $damage <= 0) {
            return false;
        }

        $now = Time::time();
        if ($now < $this->invulnerableUntil) {
            return false;
        }

        $this->currentHealth = max(0, $this->currentHealth - $damage);
        $this->invulnerableUntil = $now + $this->invulnerabilityDuration;

        if ($this->body instanceof Rigidbody2D) {
            $currentVelocity = $this->body->velocity;
            $this->body->velocity = new Vector3(
                $knockback->x,
                $knockback->y,
                $currentVelocity->z,
            );
        }

        if ($this->currentHealth <= 0) {
            $this->die();
            $this->playHitFeedback(true);
            return true;
        }

        if ($this->animation instanceof SpriteAnimation) {
            if ($this->hurtTrigger !== '') {
                $this->animation->setTrigger($this->hurtTrigger);
            } elseif ($this->hurtState !== '') {
                $this->animation->state = $this->hurtState;
            }
        }

        $attackerName = $attacker?->name ?? 'Unknown';
        Debug::info("{$this->gameObject->name} took $damage damage from $attackerName.");
        $this->playHitFeedback(false);

        return true;
    }

    private function playHitFeedback(bool $kill): void
    {
        if ($this->emitLocalHitImpact($kill)) {
            CombatFeedbackController::getInstance()?->playHitImpact($kill);
            return;
        }

        CombatFeedbackController::getInstance()?->playHitImpactAt($this->transform->position, $kill);
    }

    private function emitLocalHitImpact(bool $kill): bool
    {
        $particleSystem = $this->resolveLocalHitImpactEmitter();
        if (!$particleSystem instanceof ParticleSystem) {
            return false;
        }

        $particleCount = $kill ? 20 : 12;
        $feedback = CombatFeedbackController::getInstance();
        if ($feedback instanceof CombatFeedbackController) {
            $particleCount = $kill
                ? $feedback->killImpactParticleCount
                : $feedback->hitImpactParticleCount;
        }

        $particleSystem->clear();
        $particleSystem->emit($particleCount);
        return true;
    }

    private function resolveLocalHitImpactEmitter(): ?ParticleSystem
    {
        if ($this->hitImpactFx instanceof ParticleSystem) {
            return $this->hitImpactFx;
        }

        if ($this->hitImpactEmitterLookupFailed || $this->hitImpactEmitterPath === '') {
            return null;
        }

        try {
            $emitterTransform = $this->transform->find($this->hitImpactEmitterPath);
        } catch (\Throwable $exception) {
            Debug::warn(
                "Damageable could not resolve hit impact emitter '{$this->hitImpactEmitterPath}' on "
                . "{$this->gameObject->name}: {$exception->getMessage()}"
            );
            $this->hitImpactEmitterLookupFailed = true;
            return null;
        }

        $particleSystem = $emitterTransform?->gameObject?->getComponent(ParticleSystem::class);
        if (!$particleSystem instanceof ParticleSystem) {
            $this->hitImpactEmitterLookupFailed = true;
            return null;
        }

        $this->hitImpactFx = $particleSystem;
        return $this->hitImpactFx;
    }

    private function die(): void
    {
        if ($this->dead) {
            return;
        }

        $this->dead = true;

        if ($this->animation instanceof SpriteAnimation && $this->deathState !== '') {
            $this->animation->state = $this->deathState;
        }

        Debug::info("{$this->gameObject->name} died.");

        if ($this->destroyOnDeath) {
            $this->gameObject->destroy();
            return;
        }

        if ($this->disableGameObjectOnDeath) {
            $this->gameObject->setActive(false);
        }
    }
}
