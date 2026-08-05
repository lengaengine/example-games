<?php

declare(strict_types=1);

namespace RollerWorld\Game\Scripts\Player;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Attributes\RequireComponent;
use Lenga\Engine\Attributes\Tooltip;
use Lenga\Engine\Audio\AudioSource;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\CharacterController;
use Lenga\Engine\Core\Collision3D;
use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\Input;
use Lenga\Engine\Core\MathUtil;
use Lenga\Engine\Core\SphereRenderer;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Transform;
use Lenga\Engine\Core\Vector3;
use function abs;
use function max;

#[RequireComponent(SphereRenderer::class)]
#[RequireComponent(CharacterController::class)]
#[RequireComponent(AudioSource::class)]
final class RollerController extends Behaviour
{
    private const array DEFAULT_COLOR = [35, 221, 246, 255];
    private const array COLLISION_COLOR = [255, 176, 92, 255];
    private const array PICKUP_SHIMMER_COLOR = [212, 249, 255, 255];
    private const float COLLISION_FEEDBACK_DURATION = 0.35;
    private const float PICKUP_SHIMMER_DURATION = 0.24;

    #[Header('Movement')]
    #[Tooltip('How quickly the roller moves across the play space.')]
    #[Range(1, 20)]
    public float $moveSpeed = 8.0;

    #[Tooltip('Visual spin speed used to make the sphere feel like it is rolling.')]
    #[Range(90, 720)]
    public float $rollDegreesPerUnit = 300.0;

    #[Tooltip('Ignore tiny stick drift and accidental analog noise below this threshold.')]
    #[Range(0, 0.5)]
    public float $deadZone = 0.28;

    #[Tooltip('Allow analog virtual-axis input such as gamepad sticks. Disabled by default so connected controllers do not move the sample unexpectedly.')]
    public bool $allowAnalogInput = false;

    #[Tooltip('Enable debug logging for 3D collision and trigger callbacks while experimenting with the sample.')]
    public bool $logCollisionEvents = false;

    #[Header('Camera Relative Controls')]
    #[Tooltip('Optional camera transform used to make movement relative to the current view.')]
    public ?Transform $cameraTransform = null;

    #[Header('Respawn')]
    #[Tooltip('Optional transform used as the respawn point when the roller falls off the course.')]
    public ?Transform $respawnPoint = null;

    #[Tooltip('If the roller falls below this world Y position it snaps back to the respawn point.')]
    #[Range(-50, 5)]
    public float $fallThreshold = -6.0;

    #[Header('Audio')]
    #[Tooltip('Audio source for this roller.')]
    public ?AudioSource $audioSource = null;

    protected ?CharacterController $characterController = null;
    protected ?SphereRenderer $renderer = null;
    private float $collisionFeedbackTimer = 0.0;
    private float $pickupShimmerTimer = 0.0;
    private ?Vector3 $spawnPosition = null;
    private ?Vector3 $spawnEulerAngles = null;

    public function awake(): void
    {
        $component = $this->gameObject->getComponent(SphereRenderer::class);
        $this->renderer = $component instanceof SphereRenderer ? $component : null;
        $this->applyDefaultColor();
        $this->spawnPosition = $this->transform->position->clone();
        $this->spawnEulerAngles = $this->transform->eulerAngles->clone();
        $this->audioSource = $this->gameObject->getComponent(AudioSource::class);

    }

    public function update(): void
    {
        if ($this->transform->position->y < $this->fallThreshold) {
            $this->respawn();
            return;
        }

        $this->updateVisualFeedback();

        [$horizontal, $vertical] = $this->readMovementAxes();

        if ($horizontal === 0.0 && $vertical === 0.0) {
            $this->audioSource?->stop();
            return;
        }

        $moveDirection = $this->resolveMoveDirection($horizontal, $vertical);
        if ($moveDirection->sqrMagnitude <= 0.000001) {
            $this->audioSource?->stop();
            return;
        }

        $startPosition = $this->transform->position;
        $delta = Vector3::scaleNew(
            $moveDirection,
            $this->moveSpeed * Time::deltaTime()
        );
        $this->moveWithCharacterController($delta);

        $movedDelta = Vector3::difference($this->transform->position, $startPosition);
        if ($movedDelta->sqrMagnitude <= 0.000001) {
            $this->audioSource?->stop();
            return;
        }

        $rollAmount = $this->rollDegreesPerUnit * Time::deltaTime();
        $moveVector = $movedDelta->normalized;
        $this->transform->rotate(
            new Vector3(
                $moveVector->z * $rollAmount,
                0.0,
                -$moveVector->x * $rollAmount,
            ),
            relativeToSelf: false,
        );

        // Audio
        if ($this->audioSource) {
            if (!$this->audioSource->isPlaying) {
                $this->audioSource->play();
                $this->audioSource->loop = true;
            }
        }
    }

    private function resolveMoveDirection(float $horizontal, float $vertical): Vector3
    {
        $cameraTransform = $this->cameraTransform;
        if ($cameraTransform === null) {
            $cameraTransform = GameObject::find('Main Camera')?->transform;
            $this->cameraTransform = $cameraTransform;
        }

        if ($cameraTransform === null) {
            return new Vector3($horizontal, 0.0, -$vertical);
        }

        $up = Vector3::up();
        $forward = Vector3::projectOnPlane($cameraTransform->forward, $up)->normalized;

        if ($forward->sqrMagnitude <= 0.000001) {
            $forward = new Vector3(0.0, 0.0, -1.0);
        }

        $right = Vector3::projectOnPlane($cameraTransform->right, $up)->normalized;
        if ($right->sqrMagnitude <= 0.000001) {
            $right = Vector3::right();
        }

        $move = Vector3::sum(
            Vector3::scaleNew($right, $horizontal),
            Vector3::scaleNew($forward, -$vertical)
        );

        return $move->normalized;
    }

    private function moveWithCharacterController(Vector3 $delta): void
    {
        if ($delta->sqrMagnitude <= 0.000001) {
            return;
        }

        $characterController = $this->characterController;
        if ($characterController === null) {
            $component = $this->gameObject->getComponent(CharacterController::class);
            $characterController = $component instanceof CharacterController ? $component : null;
            $this->characterController = $characterController;
        }

        if ($characterController === null) {
            $this->transform->position = Vector3::sum($this->transform->position, $delta);
            return;
        }

        $characterController->move($delta);
    }

    private function respawn(): void
    {
        $targetPosition = $this->respawnPoint?->position?->clone()
            ?? $this->spawnPosition?->clone()
            ?? $this->transform->position->clone();

        $targetEulerAngles = $this->spawnEulerAngles?->clone() ?? Vector3::zero();

        $this->transform->position = $targetPosition;
        $this->transform->eulerAngles = $targetEulerAngles;
        $this->collisionFeedbackTimer = 0.0;
        $this->pickupShimmerTimer = 0.0;
        $this->applyDefaultColor();

        $this->audioSource?->stop();
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function readMovementAxes(): array
    {
        $horizontal = Input::getAxisRaw('Horizontal');
        $vertical = Input::getAxisRaw('Vertical');

        if (abs($horizontal) < $this->deadZone) {
            $horizontal = 0.0;
        }

        if (abs($vertical) < $this->deadZone) {
            $vertical = 0.0;
        }

        return [$horizontal, $vertical];
    }

    public function onCollisionEnter(Collision3D $collision): void
    {
        $otherName = $collision->gameObject?->name ?? 'Unknown';
        if ($otherName === 'Ground') {
            return;
        }

        $this->collisionFeedbackTimer = self::COLLISION_FEEDBACK_DURATION;
        $this->pickupShimmerTimer = 0.0;
        $this->applyColor(self::COLLISION_COLOR);
        if ($this->logCollisionEvents) {
            Debug::info('Roller collided with ' . $otherName);
        }
    }

    public function onTriggerEnter(Collision3D $collision): void
    {
        $other = $collision->gameObject;
        if ($other instanceof GameObject && $other->compareTag('Collectible')) {
            $this->pickupShimmerTimer = self::PICKUP_SHIMMER_DURATION;
        }

        $otherName = $collision->gameObject?->name ?? 'Unknown';
        if ($this->logCollisionEvents) {
            Debug::info('Roller entered trigger ' . $otherName);
        }
    }

    public function onTriggerExit(Collision3D $collision): void
    {
        $otherName = $collision->gameObject?->name ?? 'Unknown';
        if ($this->logCollisionEvents) {
            Debug::info('Roller exited trigger ' . $otherName);
        }
    }

    private function applyDefaultColor(): void
    {
        $this->applyColor(self::DEFAULT_COLOR);
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
        $progress = 1.0 - ($this->pickupShimmerTimer / self::PICKUP_SHIMMER_DURATION);
        $wave = MathUtil::sin($progress * MathUtil::PI);
        $strength = $wave * 0.35;

        $this->applyColor($this->blendColor(self::DEFAULT_COLOR, self::PICKUP_SHIMMER_COLOR, $strength));

        if ($this->pickupShimmerTimer <= 0.0) {
            $this->applyDefaultColor();
        }
    }

    /**
     * @param array{0:int, 1:int, 2:int, 3:int} $from
     * @param array{0:int, 1:int, 2:int, 3:int} $to
     * @return array{0:int, 1:int, 2:int, 3:int}
     */
    private function blendColor(array $from, array $to, float $strength): array
    {
        $t = MathUtil::clamp01($strength);

        return [
            (int) MathUtil::round(MathUtil::lerp((float) $from[0], (float) $to[0], $t)),
            (int) MathUtil::round(MathUtil::lerp((float) $from[1], (float) $to[1], $t)),
            (int) MathUtil::round(MathUtil::lerp((float) $from[2], (float) $to[2], $t)),
            (int) MathUtil::round(MathUtil::lerp((float) $from[3], (float) $to[3], $t)),
        ];
    }

    /**
     * @param array{0:int, 1:int, 2:int, 3:int} $rgba
     */
    private function applyColor(array $rgba): void
    {
        $renderer = $this->renderer;
        if ($renderer === null) {
            $component = $this->gameObject->getComponent(SphereRenderer::class);
            $renderer = $component instanceof SphereRenderer ? $component : null;
            $this->renderer = $renderer;
        }

        if ($renderer === null) {
            return;
        }

        $renderer->setColor($rgba[0], $rgba[1], $rgba[2], $rgba[3]);
    }
}
