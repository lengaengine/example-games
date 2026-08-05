<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Combat;

use Lenga\Engine\Attributes\AddComponentMenu;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Attributes\Tooltip;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\ParticleSystem;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Vector3;
use Lenga\Platformer\Scripts\Camera\CameraFollow;

#[AddComponentMenu("Combat/Combat Feedback Controller")]
final class CombatFeedbackController extends Behaviour
{
    private static ?self $instance = null;

    #[Tooltip("Brief pause applied on a normal hit.")]
    #[Range(0.0, 0.2)]
    public float $hitStopDuration = 0.05;

    #[Tooltip("Brief pause applied on a kill hit.")]
    #[Range(0.0, 0.2)]
    public float $killHitStopDuration = 0.08;

    #[Tooltip("Camera shake duration for a normal hit.")]
    #[Range(0.0, 1.0)]
    public float $hitShakeDuration = 0.14;

    #[Tooltip("Camera shake duration for a kill hit.")]
    #[Range(0.0, 1.0)]
    public float $killShakeDuration = 0.22;

    #[Tooltip("Horizontal shake magnitude for a normal hit.")]
    #[Range(0.0, 10.0)]
    public float $hitShakeMagnitudeX = 0.4;

    #[Tooltip("Vertical shake magnitude for a normal hit.")]
    #[Range(0.0, 10.0)]
    public float $hitShakeMagnitudeY = 0.3;

    #[Tooltip("Horizontal shake magnitude for a kill hit.")]
    #[Range(0.0, 10.0)]
    public float $killShakeMagnitudeX = 0.8;

    #[Tooltip("Vertical shake magnitude for a kill hit.")]
    #[Range(0.0, 10.0)]
    public float $killShakeMagnitudeY = 0.55;

    #[Tooltip("Scene object name for the authored hit impact emitter.")]
    public string $hitImpactEmitterName = 'Hit Impact FX';

    #[Tooltip("Scene object name for the authored landing dust emitter.")]
    public string $landingDustEmitterName = 'Landing Dust FX';

    #[Tooltip("How many particles to emit for a normal hit impact.")]
    #[Range(1, 64)]
    public int $hitImpactParticleCount = 12;

    #[Tooltip("How many particles to emit for a kill impact.")]
    #[Range(1, 64)]
    public int $killImpactParticleCount = 20;

    #[Tooltip("How many particles to emit for a heavy landing dust burst.")]
    #[Range(1, 64)]
    public int $landingDustParticleCount = 14;

    private bool $hitStopActive = false;
    private float $resumeAt = -1.0;
    private float $resumeTimeScale = 1.0;
    private ?CameraFollow $cameraFollow = null;
    private ?ParticleSystem $hitImpactFx = null;
    private ?ParticleSystem $landingDustFx = null;
    private bool $hitImpactFxMissingWarningLogged = false;
    private bool $landingDustFxMissingWarningLogged = false;

    public function start(): void
    {
        self::$instance = $this;
        $mainCamera = GameObject::find('Main Camera');
        $cameraFollow = $mainCamera?->getComponent(CameraFollow::class);
        $this->cameraFollow = $cameraFollow instanceof CameraFollow ? $cameraFollow : null;
    }

    public function update(): void
    {
        if (!$this->hitStopActive) {
            return;
        }

        if (Time::unscaledTime() < $this->resumeAt) {
            return;
        }

        Time::setTimeScale($this->resumeTimeScale);
        $this->hitStopActive = false;
        $this->resumeAt = -1.0;
        $this->resumeTimeScale = 1.0;
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public function playHitImpact(bool $kill = false): void
    {
        $now = Time::unscaledTime();
        $duration = $kill ? $this->killHitStopDuration : $this->hitStopDuration;
        $shakeDuration = $kill ? $this->killShakeDuration : $this->hitShakeDuration;
        $shakeMagnitudeX = $kill ? $this->killShakeMagnitudeX : $this->hitShakeMagnitudeX;
        $shakeMagnitudeY = $kill ? $this->killShakeMagnitudeY : $this->hitShakeMagnitudeY;

        if ($duration > 0.0) {
            if (!$this->hitStopActive) {
                $this->resumeTimeScale = Time::timeScale();
                Time::setTimeScale(0.0);
                $this->hitStopActive = true;
            }

            $this->resumeAt = max($this->resumeAt, $now + $duration);
        }

        if ($this->cameraFollow instanceof CameraFollow) {
            $this->cameraFollow->requestShake($shakeDuration, $shakeMagnitudeX, $shakeMagnitudeY);
        }
    }

    public function playHitImpactAt(Vector3 $worldPosition, bool $kill = false): void
    {
        $this->playHitImpact($kill);
        $particleCount = $kill ? $this->killImpactParticleCount : $this->hitImpactParticleCount;
        $this->hitImpactFx ??= $this->findParticleEmitter(
            $this->hitImpactEmitterName,
            $this->hitImpactFxMissingWarningLogged,
        );
        $this->emitBurst($this->hitImpactFx, $worldPosition, $particleCount);
    }

    public function playLandingDustAt(Vector3 $worldPosition): void
    {
        $this->landingDustFx ??= $this->findParticleEmitter(
            $this->landingDustEmitterName,
            $this->landingDustFxMissingWarningLogged,
        );
        $this->emitBurst($this->landingDustFx, $worldPosition, $this->landingDustParticleCount);
    }

    private function findParticleEmitter(string $gameObjectName, bool &$warningLogged): ?ParticleSystem
    {
        if ($gameObjectName === '') {
            return null;
        }

        try {
            $gameObject = GameObject::find($gameObjectName);
        } catch (\Throwable $exception) {
            if (!$warningLogged) {
                Debug::warn(
                    "CombatFeedbackController could not resolve particle emitter '$gameObjectName': "
                    . $exception->getMessage()
                );
                $warningLogged = true;
            }
            return null;
        }

        if (!$gameObject instanceof GameObject) {
            if (!$warningLogged) {
                Debug::warn("CombatFeedbackController could not find particle emitter '$gameObjectName'.");
                $warningLogged = true;
            }
            return null;
        }

        $particleSystem = $gameObject->getComponent(ParticleSystem::class);
        if (!$particleSystem instanceof ParticleSystem) {
            if (!$warningLogged) {
                Debug::warn(
                    "CombatFeedbackController expected '$gameObjectName' to have a ParticleSystem component."
                );
                $warningLogged = true;
            }
            return null;
        }

        $warningLogged = false;
        return $particleSystem;
    }

    private function emitBurst(?ParticleSystem $particleSystem, Vector3 $worldPosition, int $count): void
    {
        if (!$particleSystem instanceof ParticleSystem || $count <= 0) {
            return;
        }

        $particleSystem->gameObject->transform->position = new Vector3(
            $worldPosition->x,
            $worldPosition->y,
            $worldPosition->z,
        );
        $particleSystem->clear();
        $particleSystem->emit($count);
    }
}
