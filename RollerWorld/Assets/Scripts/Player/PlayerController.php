<?php

declare(strict_types=1);

namespace RollerWorld\Game\Scripts\Player;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Attributes\RequireComponent;
use Lenga\Engine\Attributes\SerializeField;
use Lenga\Engine\Attributes\Tooltip;
use Lenga\Engine\Audio\AudioSource;
use Lenga\Engine\Core\Application;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Collision3D;
use Lenga\Engine\Core\Color;
use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\Input;
use Lenga\Engine\Core\MathUtil;
use Lenga\Engine\Core\Rigidbody3D;
use Lenga\Engine\Core\SphereRenderer;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Transform;
use Lenga\Engine\Core\Vector3;

#[RequireComponent(SphereRenderer::class)]
#[RequireComponent(Rigidbody3D::class)]
#[RequireComponent(AudioSource::class)]
final class PlayerController extends Behaviour
{
    #[Tooltip('Optional transform used as the respawn point when the roller falls off the course.')]
    public ?Transform $respawnPoint = null;
    #[Tooltip('If the roller falls below this world Y position it snaps back to the respawn point.')]
    #[Range(-50, 5)]
    public float $fallThreshold = -6.0;
    #[Header("References")]
    #[SerializeField]
    private SphereRenderer $sphereRenderer;
    #[SerializeField]
    private Rigidbody3D $body;
    #[Header("Movement")]
    #[SerializeField]
    private float $rollForce = 20000;
    #[SerializeField]
    private float $deadZone = 0.2;
    private array $movementInputs = [];
    #[Header('Spawning Settings')]
    #[Tooltip('Optional world-space position where the roller spawns. If left empty it will use the roller\'s initial position in the scene.')]
    #[SerializeField]
    private ?Vector3 $spawnPosition = null;
    #[Tooltip('Optional world-space Euler angles applied to the roller when it spawns or respawns. If left empty it will use the roller\'s initial rotation.')]
    #[SerializeField]
    private ?Vector3 $spawnEulerAngles = null;
    private bool $isGrounded = true {
        get {
            return $this->isGrounded;
        }

        set {
            $this->isGrounded = $value;

            if ($this->logCollisionEvents) {
                Debug::log("Roller isGrounded set to " . ($this->isGrounded ? "true" : "false"));
            }
        }
    }

    #[Header("Audio")]
    #[Tooltip('Audio source for this roller.')]
    #[SerializeField]
    private AudioSource $audioSource;
    private bool $resumeAudioAfterPause = false;

    #[Header("Visual Feedback")]
    #[SerializeField]
    private Color $defaultColor;
    #[SerializeField]
    private Color $collisionColor;
    #[SerializeField]
    private float $collisionFeedbackDuration = 0.35;
    #[SerializeField]
    private Color $pickupShimmerColor;
    #[SerializeField]
    private float $pickupShimmerDuration = 0.24;
    private float $collisionFeedbackTimer = 0.0;
    private float $pickupShimmerTimer = 0.0;

    #[Header("Debug")]
    #[Tooltip('Enable debug logging for 3D collision and trigger callbacks while experimenting with the sample.')]
    #[SerializeField]
    private bool $logCollisionEvents = false;

    public function awake(): void
    {
        $this->defaultColor = Color::fromRGBA(35, 221, 246, 255);
        $this->collisionColor = Color::fromRGBA(255, 176, 92, 255);
        $this->pickupShimmerColor = Color::fromRGBA(212, 249, 255, 255);

        $this->sphereRenderer = $this->gameObject->getComponent(SphereRenderer::class);
        $this->body = $this->gameObject->getComponent(Rigidbody3D::class);
        $this->audioSource = $this->gameObject->getComponent(AudioSource::class);

        $this->spawnPosition = $this->transform->position->clone();
        $this->spawnEulerAngles = $this->transform->eulerAngles->clone();

        $this->applyDefaultColor();
    }

    public function onEnable(): void
    {
        $this->onEvent("game.paused", function () {
            $this->onGamePaused();
        });

        $this->onEvent("game.resumed", function () {
            $this->onGameResumed();
        });
    }

    private function applyDefaultColor(): void
    {
        $this->applyColor($this->defaultColor);
    }

    private function applyColor(Color $color): void
    {
        if (!$this->sphereRenderer->material) {
            return;
        }

        $this->sphereRenderer->material->color = $color;
    }

    public function update(): void
    {
        if ($this->transform->position->y < $this->fallThreshold) {
            $this->respawn();
            return;
        }

        $this->updateVisualFeedback();

        $moveHorizontal = Input::getAxis("Horizontal");
        $moveVertical = Input::getAxis("Vertical");
        $this->movementInputs = [$moveHorizontal, $moveVertical];
    }

    private function respawn(): void
    {
        $targetPosition = $this->respawnPoint?->position?->clone()
            ?? $this->spawnPosition?->clone()
            ?? Vector3::zero();

        $targetEulerAngles = $this->spawnEulerAngles?->clone() ?? Vector3::zero();

        $this->transform->position = $targetPosition;
        $this->transform->eulerAngles = $targetEulerAngles;
        $this->body->velocity = Vector3::zero();
        $this->body->angularVelocity = Vector3::zero();
        $this->collisionFeedbackTimer = 0.0;
        $this->pickupShimmerTimer = 0.0;

        $this->applyDefaultColor();
        $this->audioSource?->stop();
    }

    private function updateVisualFeedback(): void
    {
        if ($this->collisionFeedbackTimer > 0.0) {
            $this->collisionFeedbackTimer = max(0.0, $this->collisionFeedbackTimer - Time::deltaTime());
            if ($this->collisionFeedbackTimer <= 0.0) {
                $this->applyDefaultColor();
            }
            return;
        }

        if ($this->pickupShimmerTimer <= 0.0) {
            return;
        }

        $this->pickupShimmerTimer = max(0.0, $this->pickupShimmerTimer - Time::deltaTime());
        $progress = 1.0 - ($this->pickupShimmerTimer / $this->pickupShimmerDuration);
        $wave = MathUtil::sin($progress * MathUtil::PI);
        $strength = $wave * 0.35;

        $this->applyColor($this->blendColor($this->defaultColor, $this->pickupShimmerColor, $strength));

        if ($this->pickupShimmerTimer <= 0.0) {
            $this->applyDefaultColor();
        }
    }

    private function blendColor(Color $from, Color $to, float $strength): Color
    {
        $t = MathUtil::clamp01($strength);

        return Color::lerp($from, $to, $t);
    }

    public function fixedUpdate(): void
    {
        [$moveHorizontal, $moveVertical] = $this->movementInputs;
        $movement = new Vector3($moveHorizontal, 0.0, $moveVertical);

        if (
            (abs($moveHorizontal) > 0 || abs($moveVertical) > 0) &&
            $this->isGrounded
        ) {
            $this->body->addForce(Vector3::scaleNew($movement, $this->rollForce));
        }

        if ($this->body->velocity->magnitude || $this->body->angularVelocity->magnitude) {
            $applicationIsPaused = Application::isPaused();
            $applicationIsRunning = !$applicationIsPaused;
            $audioSourceIsStopped = !$this->audioSource->isPlaying && !$this->audioSource->isPaused;

            if ($applicationIsPaused && $this->audioSource->isPlaying) {
                $this->audioSource->pause();
            } elseif ($applicationIsRunning) {
                if ($audioSourceIsStopped) {
                    $this->audioSource->play();
                } elseif ($this->audioSource->isPaused) {
                    $this->audioSource->resume();
                }
            }
        } elseif ($this->audioSource->isPlaying) {
            $this->audioSource->pause();
        }
    }

    public function onCollisionEnter(Collision3D $collision): void
    {
        $otherGameObject = $collision->gameObject;
        $otherGameObjectTag = $otherGameObject?->tag ?? 'Unknown';
        $otherGameObjectName = $otherGameObject?->name ?? 'Unknown';
        if ($otherGameObjectTag === 'Ground') {
            $this->isGrounded = true;
            return;
        }

        $this->collisionFeedbackTimer = $this->collisionFeedbackDuration;
        $this->pickupShimmerTimer = 0.0;
        $this->applyColor($this->collisionColor);

        if ($this->logCollisionEvents) {
            Debug::info("Roller collided with $otherGameObjectName");
        }
    }

    public function onCollisionExit(Collision3D $collision): void
    {
        $otherGameObject = $collision->gameObject;
        $otherGameObjectTag = $otherGameObject->tag ?? 'Unknown';
        $otherGameObjectName = $otherGameObject?->name ?? 'Unknown';
        if ($otherGameObjectTag === 'Ground') {
            $this->isGrounded = false;
            return;
        }

        if ($this->logCollisionEvents) {
            Debug::info("Roller onCollisionExit with $otherGameObjectName");
        }
    }

    public function onTriggerEnter(Collision3D $collision): void
    {
        $otherGameObject = $collision->gameObject;

        if ($otherGameObject instanceof GameObject && $otherGameObject->compareTag("Collectible")) {
            $this->pickupShimmerTimer = $this->pickupShimmerDuration;
        }

        $otherGameObjectName = $otherGameObject->name ?? 'Unknown';
        if ($this->logCollisionEvents) {
            Debug::info("Roller entered trigger $otherGameObjectName");
        }
    }

    public function onTriggerExit(Collision3D $collision): void
    {
        $otherGameObjectName = $collision->gameObject?->name ?? 'Unknown';
        if ($this->logCollisionEvents) {
            Debug::info("Roller exited trigger $otherGameObjectName");
        }
    }

    private function onGamePaused(): void
    {
        $this->resumeAudioAfterPause = false;
        if ($this->audioSource->isPlaying) {
            $this->resumeAudioAfterPause = $this->audioSource->pause();
        }
    }

    private function onGameResumed(): void
    {
        if ($this->resumeAudioAfterPause) {
            $this->audioSource->resume();
            $this->resumeAudioAfterPause = false;
        }
    }
}
