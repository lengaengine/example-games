<?php /** @noinspection ALL */

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Player;

use Lenga\Engine\Attributes\AddComponentMenu;
use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Attributes\Tooltip;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\BoxCollider2D;
use Lenga\Engine\Core\Collision2D;
use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\Input;
use Lenga\Engine\Core\MathUtil;
use Lenga\Engine\Core\ParticleSystem;
use Lenga\Engine\Core\Rigidbody2D;
use Lenga\Engine\Core\SpriteAnimation;
use Lenga\Engine\Core\SpriteRenderer;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Vector3;
use Lenga\Platformer\Scripts\Combat\CombatFeedbackController;
use Lenga\Platformer\Scripts\Combat\MeleeHitbox;

#[AddComponentMenu("Player/PlayerController")]
final class PlayerController extends Behaviour
{
    private const ENGINE_GRAVITY = 980.0;

    #[Header("Movement Settings")]

    #[Tooltip("The movement speed of the player.")]
    #[Range(10, 50)]
    public float $speed = 20.0;

    #[Tooltip("How high the jump should rise from takeoff to the apex.")]
    #[Range(8.0, 160.0)]
    public float $jumpHeight = 40.0;

    #[Tooltip("How long it should take to reach the jump apex.")]
    #[Range(0.1, 1.0)]
    public float $jumpTimeToPeak = 0.35;

    #[Tooltip("How long the descent should take from apex back to the ground.")]
    #[Range(0.1, 1.5)]
    public float $jumpTimeToDescent = 0.28;

    #[Tooltip("How quickly the player reaches the target horizontal speed while grounded.")]
    #[Range(20, 600)]
    public float $groundAcceleration = 220.0;

    #[Tooltip("How quickly the player slows down or reverses direction while grounded.")]
    #[Range(20, 600)]
    public float $groundDeceleration = 320.0;

    #[Tooltip("How quickly the player can redirect in the air.")]
    #[Range(20, 600)]
    public float $airAcceleration = 140.0;

    #[Tooltip("How quickly the player loses horizontal speed in the air with no input.")]
    #[Range(20, 600)]
    public float $airDeceleration = 80.0;

    #[Tooltip("How long after leaving the ground the player can still jump.")]
    #[Range(0.0, 0.3)]
    public float $coyoteTime = 0.12;

    #[Tooltip("How long a jump press is buffered before landing or coyote-jumping.")]
    #[Range(0.0, 0.3)]
    public float $jumpBufferTime = 0.12;

    #[Tooltip("If enabled, releasing jump early produces a shorter jump, Mario-style.")]
    public bool $enableVariableJumpHeight = true;

    #[Tooltip("Releases upward velocity early when the jump button is released.")]
    #[Range(0.1, 1.0)]
    public float $jumpCutVelocityFactor = 0.45;

    #[Tooltip("If enabled, the player can jump again while airborne.")]
    public bool $enableDoubleJump = false;

    #[Tooltip("How many additional jumps are available after leaving the ground.")]
    #[Range(1, 3)]
    public int $extraJumpCount = 1;

    #[Tooltip("If enabled, gravity softens near the apex to create a brief jump hang.")]
    public bool $enableJumpHang = true;

    #[Tooltip("Vertical speed threshold used to detect the jump apex hang zone.")]
    #[Range(2.0, 80.0)]
    public float $jumpHangVelocityThreshold = 28.0;

    #[Tooltip("Gravity multiplier applied while inside the apex hang zone.")]
    #[Range(0.1, 1.0)]
    public float $jumpHangGravityFactor = 0.55;

    #[Tooltip("Extra horizontal control granted while hanging near the apex.")]
    #[Range(1.0, 2.0)]
    public float $jumpHangAirControlMultiplier = 1.2;

    #[Tooltip("If enabled, landing from a meaningful fall briefly reduces control for weight.")]
    public bool $enableLandingRecovery = true;

    #[Tooltip("Minimum downward speed needed to trigger landing recovery.")]
    #[Range(20.0, 300.0)]
    public float $hardLandingMinSpeed = 120.0;

    #[Tooltip("How long landing recovery lasts after a hard landing.")]
    #[Range(0.0, 0.2)]
    public float $landingRecoveryTime = 0.06;

    #[Range(50, 300)]
    public float $terminalVelocity = 300;

    #[Tooltip("How much of the current horizontal speed is preserved when an attack starts.")]
    #[Range(0.0, 1.0)]
    public float $attackSlideFactor = 0.4;

    #[Tooltip("Optional child particle emitter used for landing dust bursts.")]
    public string $landingDustEmitterPath = 'Landing Dust FX';

    #[Tooltip("The minimum slide speed preserved when attacking from a run.")]
    #[Range(0.0, 50.0)]
    public float $attackSlideMinSpeed = 6.0;

    #[Tooltip("How quickly the attack slide eases back toward zero.")]
    #[Range(20.0, 600.0)]
    public float $attackSlideDecay = 140.0;

    #[Header("Combat Settings")]
    #[Range(1, 10)]
    public int $lightAttackDamage = 1;

    #[Range(0.0, 1.0)]
    public float $lightAttackHitStart = 0.04;

    #[Range(0.0, 1.0)]
    public float $lightAttackHitEnd = 0.16;

    #[Range(0.0, 400.0)]
    public float $lightAttackKnockbackX = 160.0;

    #[Range(-400.0, 400.0)]
    public float $lightAttackKnockbackY = -120.0;

    #[Range(1, 10)]
    public int $heavyAttackDamage = 2;

    #[Range(0.0, 1.0)]
    public float $heavyAttackHitStart = 0.08;

    #[Range(0.0, 1.0)]
    public float $heavyAttackHitEnd = 0.24;

    #[Range(0.0, 400.0)]
    public float $heavyAttackKnockbackX = 220.0;

    #[Range(-400.0, 400.0)]
    public float $heavyAttackKnockbackY = -150.0;

    #[Header("Input Settings")]
    #[Range(0.0, 0.5)]
    public float $deadZone = 0.1;

    #[Range(0.3, 1.0)]
    public float $runThreshold = 0.5;

    protected ?Rigidbody2D $body = null;
    protected ?BoxCollider2D $collider = null;
    protected ?SpriteRenderer  $renderer = null;
    protected ?SpriteAnimation  $animation = null;
    protected ?MeleeHitbox $attackHitbox = null;
    protected ?ParticleSystem $landingDustFx = null;
    protected float $moveInput = 0.0;
    protected float $jumpBufferedUntil = -1.0;
    protected float $coyoteExpiresAt = -1.0;
    protected bool $jumpCutRequested = false;
    protected bool $wasGrounded = false;
    protected int $facingDirection = 1;
    protected float $attackSlideVelocityX = 0.0;
    protected int $remainingAirJumps = 0;
    protected float $lastAirborneVelocityY = 0.0;
    protected float $landingRecoveryUntil = -1.0;
    protected bool $attackHitboxActive = false;
    protected float $attackHitboxStartsAt = -1.0;
    protected float $attackHitboxEndsAt = -1.0;
    protected int $attackDamage = 1;
    protected float $attackKnockbackX = 0.0;
    protected float $attackKnockbackY = 0.0;
    protected string $jumpState = 'Jump';
    protected string $lightAttackState = 'Attack Light';
    protected string $heavyAttackState = 'Attack Heavy';
    protected bool $landingDustEmitterLookupFailed = false;

    public function start(): void
    {
        $this->body = $this->gameObject->getComponent(Rigidbody2D::class);
        $this->collider = $this->gameObject->getComponent(BoxCollider2D::class);

        if ($this->body === null) {
            Debug::warn("PlayerController requires a Rigidbody2D component.");
        }

        if ($this->collider === null) {
            Debug::warn("PlayerController benefits from a BoxCollider2D for grounded feedback.");
        }

        $this->renderer = $this->gameObject->getComponent(SpriteRenderer::class);

        if ($this->renderer === null) {
            Debug::warn("PlayerController requires a SpriteRenderer component.");
        }

        $this->animation = $this->gameObject->getComponent(SpriteAnimation::class);

        if ($this->animation === null) {
            Debug::warn("PlayerController requires a SpriteAnimation component.");
        }

        $attackHitbox = $this->gameObject->getComponentInChildren(MeleeHitbox::class, true);
        $this->attackHitbox = $attackHitbox instanceof MeleeHitbox ? $attackHitbox : null;
    }

    public function update(): void
    {
        $this->moveInput = Input::getAxis("Horizontal");
        if (MathUtil::abs($this->moveInput) <= $this->deadZone) {
            $this->moveInput = 0.0;
        }

        if ($this->moveInput > 0.0) {
            $this->facingDirection = 1;
        } elseif ($this->moveInput < 0.0) {
            $this->facingDirection = -1;
        }

        if (Input::getButtonDown("Jump")) {
            $this->jumpBufferedUntil = Time::time() + $this->jumpBufferTime;
        }

        if (Input::getButtonUp("Jump")) {
            $this->jumpCutRequested = $this->enableVariableJumpHeight;
        }

        $this->updatePresentation();

        if (Input::getButtonDown("Light Attack")) {
            $this->lightAttack();
        }

        if (Input::getButtonDown("Heavy Attack")) {
            $this->heavyAttack();
        }
    }

    public function fixedUpdate(): void
    {
        if (!$this->body) {
            return;
        }

        $now = Time::time();
        $this->updateAttackHitboxState($now);
        $fixedDeltaTime = Time::fixedDeltaTime();
        $velocity = $this->body->velocity->clone();
        $grounded = $this->body->isGrounded();
        $justLanded = !$this->wasGrounded && $grounded;

        if ($justLanded) {
            $this->handleLanding($now);
        }

        if ($grounded) {
            $this->coyoteExpiresAt = $now + $this->coyoteTime;
            $this->remainingAirJumps = $this->resolveAvailableAirJumps();
        }

        if ($this->canConsumeJump($now)) {
            $this->cancelAttackForJump();
            $velocity->y = $this->getJumpLaunchVelocity();
            $grounded = false;
            $this->jumpBufferedUntil = -1.0;
            $this->coyoteExpiresAt = -1.0;
            $this->jumpCutRequested = false;
        } elseif ($this->canConsumeAirJump($now, $grounded)) {
            $this->cancelAttackForJump();
            $velocity->y = $this->getJumpLaunchVelocity();
            $grounded = false;
            $this->jumpBufferedUntil = -1.0;
            $this->jumpCutRequested = false;
            $this->remainingAirJumps = max(0, $this->remainingAirJumps - 1);
        }

        if ($this->jumpCutRequested && $velocity->y < 0.0) {
            $velocity->y = MathUtil::max(
                $velocity->y,
                $this->getJumpLaunchVelocity() * $this->jumpCutVelocityFactor
            );
            $this->jumpCutRequested = false;
        }

        if ($this->isAttacking()) {
            if ($grounded) {
                $this->attackSlideVelocityX = MathUtil::moveTowards(
                    $this->attackSlideVelocityX,
                    0.0,
                    $this->attackSlideDecay * $fixedDeltaTime
                );
                $velocity->x = MathUtil::moveTowards(
                    $velocity->x,
                    $this->attackSlideVelocityX,
                    $this->attackSlideDecay * $fixedDeltaTime
                );
            }
        } else {
            $targetSpeed = $this->moveInput * $this->speed;
            $rate = $this->resolveHorizontalRate($velocity->x, $targetSpeed, $grounded);
            if (!$grounded && $this->isInJumpHangZone($velocity->y)) {
                $rate *= $this->jumpHangAirControlMultiplier;
            }
            if ($grounded && $now < $this->landingRecoveryUntil) {
                $targetSpeed = 0.0;
                $rate = MathUtil::max($rate, $this->groundDeceleration);
            }
            $velocity->x = MathUtil::moveTowards($velocity->x, $targetSpeed, $rate * $fixedDeltaTime);
        }

        $this->body->gravityScale = $this->resolveGravityScale($velocity->y, $grounded);
        $velocity->y = MathUtil::clamp($velocity->y, $this->getJumpLaunchVelocity(), $this->terminalVelocity);
        $this->body->velocity = $velocity;
        $this->applyAnimationState($grounded);
        if (!$grounded) {
            $this->lastAirborneVelocityY = $velocity->y;
        }
        $this->wasGrounded = $grounded;
    }

    private function updatePresentation(): void
    {
        if ($this->moveInput !== 0.0) {
            if ($this->renderer) {
                $this->renderer->flipX = $this->moveInput < 0.0;
            }
        }

        if ($this->attackHitbox instanceof MeleeHitbox) {
            $this->attackHitbox->syncFacingDirection($this->facingDirection);
        }
    }

    public function onCollisionEnter2D(Collision2D $collision): void
    {
        $otherName = $collision->gameObject?->name ?? 'Unknown';
        // TODO: Do something with the collision
    }

    private function lightAttack(): void
    {
        $this->startAttack($this->lightAttackState, "Light Attack");
    }

    private function heavyAttack(): void
    {
        $this->startAttack($this->heavyAttackState, "Heavy Attack");
    }

    private function canConsumeJump(float $now): bool
    {
        return $this->jumpBufferedUntil >= $now && $this->coyoteExpiresAt >= $now;
    }

    private function canConsumeAirJump(float $now, bool $grounded): bool
    {
        if (!$this->enableDoubleJump || $grounded) {
            return false;
        }

        return $this->jumpBufferedUntil >= $now && $this->remainingAirJumps > 0;
    }

    private function resolveAvailableAirJumps(): int
    {
        if (!$this->enableDoubleJump) {
            return 0;
        }

        return (int)max(0, $this->extraJumpCount);
    }

    private function handleLanding(float $now): void
    {
        $isHardLanding = $this->lastAirborneVelocityY >= $this->hardLandingMinSpeed;
        if ($isHardLanding) {
            if (!$this->emitLandingDustBurst()) {
                CombatFeedbackController::getInstance()?->playLandingDustAt($this->resolveLandingDustPosition());
            }
        }

        if (!$this->enableLandingRecovery) {
            $this->landingRecoveryUntil = -1.0;
            return;
        }

        if ($isHardLanding) {
            $this->landingRecoveryUntil = $now + $this->landingRecoveryTime;
            return;
        }

        $this->landingRecoveryUntil = -1.0;
    }

    private function resolveLandingDustPosition(): Vector3
    {
        if ($this->collider instanceof BoxCollider2D) {
            $offset = $this->collider->offset;
            $size = $this->collider->size;
            return new Vector3(
                $this->transform->position->x + $offset->x,
                $this->transform->position->y + $offset->y + ($size->y * 0.5),
                $this->transform->position->z,
            );
        }

        return $this->transform->position;
    }

    private function emitLandingDustBurst(): bool
    {
        $particleSystem = $this->resolveLandingDustEmitter();
        if (!$particleSystem instanceof ParticleSystem) {
            return false;
        }

        $particleCount = 14;
        $feedback = CombatFeedbackController::getInstance();
        if ($feedback instanceof CombatFeedbackController) {
            $particleCount = $feedback->landingDustParticleCount;
        }

        $particleSystem->clear();
        $particleSystem->emit($particleCount);
        return true;
    }

    private function resolveLandingDustEmitter(): ?ParticleSystem
    {
        if ($this->landingDustFx instanceof ParticleSystem) {
            return $this->landingDustFx;
        }

        if ($this->landingDustEmitterLookupFailed || $this->landingDustEmitterPath === '') {
            return null;
        }

        try {
            $emitterTransform = $this->transform->find($this->landingDustEmitterPath);
        } catch (\Throwable $exception) {
            Debug::warn(
                "PlayerController could not resolve landing dust emitter '{$this->landingDustEmitterPath}': "
                . $exception->getMessage()
            );
            $this->landingDustEmitterLookupFailed = true;
            return null;
        }

        $particleSystem = $emitterTransform?->gameObject?->getComponent(ParticleSystem::class);
        if (!$particleSystem instanceof ParticleSystem) {
            $this->landingDustEmitterLookupFailed = true;
            return null;
        }

        $this->landingDustFx = $particleSystem;
        return $this->landingDustFx;
    }

    private function isInJumpHangZone(float $velocityY): bool
    {
        return $this->enableJumpHang && MathUtil::abs($velocityY) <= $this->jumpHangVelocityThreshold;
    }

    private function resolveGravityScale(float $velocityY, bool $grounded): float
    {
        if ($grounded) {
            return $this->getJumpFallGravityScale();
        }

        if ($this->isInJumpHangZone($velocityY)) {
            return $this->getJumpAscentGravityScale() * $this->jumpHangGravityFactor;
        }

        return $velocityY < 0.0
            ? $this->getJumpAscentGravityScale()
            : $this->getJumpFallGravityScale();
    }

    private function resolveHorizontalRate(float $currentSpeed, float $targetSpeed, bool $grounded): float
    {
        if (!$grounded) {
            if ($targetSpeed === 0.0) {
                return $this->airDeceleration;
            }

            return MathUtil::sign($targetSpeed) !== 0.0 && MathUtil::sign($targetSpeed) !== MathUtil::sign($currentSpeed)
                ? $this->groundDeceleration
                : $this->airAcceleration;
        }

        if ($targetSpeed === 0.0) {
            return $this->groundDeceleration;
        }

        return MathUtil::sign($targetSpeed) !== 0.0 && MathUtil::sign($targetSpeed) !== MathUtil::sign($currentSpeed)
            ? $this->groundDeceleration
            : $this->groundAcceleration;
    }

    private function isAttacking(): bool
    {
        $state = $this->animation?->state ?? '';
        return $state === $this->lightAttackState || $state === $this->heavyAttackState;
    }

    private function startAttack(string $stateName, string $triggerName): void
    {
        if ($this->animation === null || $this->isAttacking()) {
            return;
        }

        $this->animation->resetTrigger("Light Attack");
        $this->animation->resetTrigger("Heavy Attack");

        $this->animation->state = $stateName;
        if ($this->animation->state !== $stateName) {
            $this->animation->setTrigger($triggerName);
        }

        if ($this->body === null) {
            return;
        }

        $slideSource = $this->body->velocity->x;
        if (MathUtil::abs($slideSource) <= $this->deadZone) {
            $slideSource = $this->moveInput !== 0.0
                ? $this->moveInput * $this->speed
                : $this->facingDirection * $this->attackSlideMinSpeed;
        }

        $slideVelocity = $slideSource * $this->attackSlideFactor;
        if (MathUtil::abs($slideVelocity) < $this->attackSlideMinSpeed) {
            $slideVelocity = MathUtil::sign($slideSource) * $this->attackSlideMinSpeed;
        }

        $this->attackSlideVelocityX = $slideVelocity;
        $this->queueAttackHitWindow($stateName);
    }

    private function cancelAttackForJump(): void
    {
        if ($this->animation === null || !$this->isAttacking()) {
            return;
        }

        $this->animation->resetTrigger("Light Attack");
        $this->animation->resetTrigger("Heavy Attack");
        $this->animation->setBool("IsAirborne", true);
        $this->animation->state = $this->jumpState;
        $this->clearAttackHitWindow();
    }

    private function applyAnimationState(bool $grounded): void
    {
        if ($this->animation === null) {
            return;
        }

        $absoluteMoveInput = MathUtil::abs($this->moveInput);
        $isRunning = $absoluteMoveInput > $this->runThreshold;
        $isWalking = $absoluteMoveInput > $this->deadZone && !$isRunning;

        $this->animation->setBool("IsWalking", $isWalking);
        $this->animation->setBool("IsRunning", $isRunning);
        $this->animation->setBool("IsAirborne", !$grounded);

        if (!$grounded) {
            if (!$this->isAttacking()) {
                $this->animation->state = $this->jumpState;
            }
            return;
        }

        if ($this->animation->state === $this->jumpState) {
            if ($isRunning) {
                $this->animation->state = "Dash";
                return;
            }

            if ($isWalking) {
                $this->animation->state = "Walk";
                return;
            }

            $this->animation->state = "Idle";
        }
    }

    private function getJumpLaunchVelocity(): float
    {
        $timeToPeak = MathUtil::max(0.01, $this->jumpTimeToPeak);
        return -(2.0 * $this->jumpHeight) / $timeToPeak;
    }

    private function getJumpAscentGravityScale(): float
    {
        $timeToPeak = MathUtil::max(0.01, $this->jumpTimeToPeak);
        $gravityMagnitude = (2.0 * $this->jumpHeight) / ($timeToPeak * $timeToPeak);
        return $gravityMagnitude / self::ENGINE_GRAVITY;
    }

    private function getJumpFallGravityScale(): float
    {
        $timeToDescent = MathUtil::max(0.01, $this->jumpTimeToDescent);
        $gravityMagnitude = (2.0 * $this->jumpHeight) / ($timeToDescent * $timeToDescent);
        return $gravityMagnitude / self::ENGINE_GRAVITY;
    }

    private function queueAttackHitWindow(string $stateName): void
    {
        $now = Time::time();
        if ($stateName === $this->heavyAttackState) {
            $this->attackDamage = $this->heavyAttackDamage;
            $this->attackKnockbackX = $this->heavyAttackKnockbackX;
            $this->attackKnockbackY = $this->heavyAttackKnockbackY;
            $this->attackHitboxStartsAt = $now + $this->heavyAttackHitStart;
            $this->attackHitboxEndsAt = $now + $this->heavyAttackHitEnd;
            return;
        }

        $this->attackDamage = $this->lightAttackDamage;
        $this->attackKnockbackX = $this->lightAttackKnockbackX;
        $this->attackKnockbackY = $this->lightAttackKnockbackY;
        $this->attackHitboxStartsAt = $now + $this->lightAttackHitStart;
        $this->attackHitboxEndsAt = $now + $this->lightAttackHitEnd;
    }

    private function updateAttackHitboxState(float $now): void
    {
        if (!$this->attackHitbox instanceof MeleeHitbox) {
            return;
        }

        $attackStillPlaying = $this->isAttacking();
        $shouldEnableHitbox = $attackStillPlaying
            && $this->attackHitboxStartsAt >= 0.0
            && $this->attackHitboxEndsAt >= $this->attackHitboxStartsAt
            && $now >= $this->attackHitboxStartsAt
            && $now <= $this->attackHitboxEndsAt;

        if ($shouldEnableHitbox && !$this->attackHitboxActive) {
            $this->attackHitbox->beginSwing(
                $this->attackDamage,
                $this->attackKnockbackX,
                $this->attackKnockbackY,
                $this->facingDirection,
            );
            $this->attackHitboxActive = true;
        }

        if ((!$shouldEnableHitbox && $this->attackHitboxActive) || (!$attackStillPlaying && $this->attackHitboxStartsAt >= 0.0)) {
            $this->clearAttackHitWindow();
        }
    }

    private function clearAttackHitWindow(): void
    {
        if ($this->attackHitbox instanceof MeleeHitbox && $this->attackHitboxActive) {
            $this->attackHitbox->endSwing();
        }

        $this->attackHitboxActive = false;
        $this->attackHitboxStartsAt = -1.0;
        $this->attackHitboxEndsAt = -1.0;
    }
}
