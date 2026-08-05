<?php

declare(strict_types=1);

namespace Lenga\Pong\Scripts;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\RequireComponent;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\Input;
use Lenga\Engine\Core\MathUtil;
use Lenga\Engine\Core\Rigidbody2D;
use Lenga\Engine\Core\SpriteAnimation;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Vector3;

#[RequireComponent(Rigidbody2D::class)]
final class Paddle extends Behaviour
{
    const int SPEED_MULTIPLIER = 1000;

    #[Header("Input")]
    public string $axisName = "";
    public bool $isAiDriven = false;

    #[Header("Movement")]
    public float $speed = 90.0;

    #[Header("AI")]
    public string $trackedBallName = "Ball";
    public float $aiReactionRate = 7.0;
    public float $aiDeadZone = 0.35;
    public float $aiErrorMargin = 0.9;

    private ?Rigidbody2D $body = null;
    private ?SpriteAnimation $spriteAnimation = null;
    private ?GameObject $trackedBall = null;
    private ?Rigidbody2D $trackedBallBody = null;
    private $verticalInput = 0.0;
    private float $aiSteering = 0.0;

    public function awake(): void
    {
        $this->body = $this->gameObject->getComponent(Rigidbody2D::class);
        $this->spriteAnimation = $this->gameObject->getComponent(SpriteAnimation::class);

        if ($this->isAiDriven) {
            $this->cacheTrackedBall();
        }
    }

    public function start(): void
    {
        $this->verticalInput = 0.0;
    }

    public function update(): void
    {
        $this->verticalInput = Input::getAxis($this->axisName);
    }

    public function fixedUpdate(): void
    {
        if ($this->isAiDriven) {
            $this->handleAiInput();
        } else {
            $this->handleHumanInput();
        }
    }

    private function handleHumanInput(): void
    {
        if ($this->body === null) {
            $this->body = $this->gameObject->getComponent(Rigidbody2D::class);
        }

        if ($this->body === null) {
            return;
        }

        $this->body->velocity = new Vector3(
            0.0,
            $this->verticalInput * $this->speed * self::SPEED_MULTIPLIER * Time::deltaTime(),
            0.0
        );

        if ($this->verticalInput) {
            $this->spriteAnimation?->setBool("IsMoving", true);
            return;
        }

        $this->spriteAnimation?->setBool("IsMoving", false);
    }

    private function handleAiInput(): void
    {
        if ($this->body === null) {
            $this->body = $this->gameObject->getComponent(Rigidbody2D::class);
        }

        if ($this->body === null) {
            return;
        }

        if ($this->trackedBall === null || !$this->trackedBall->activeInHierarchy) {
            $this->cacheTrackedBall();
        }

        if ($this->trackedBall === null) {
            $this->applyAiMovement(0.0);
            return;
        }

        if ($this->trackedBallBody === null) {
            $ballBody = $this->trackedBall->getComponent(Rigidbody2D::class);
            $this->trackedBallBody = $ballBody instanceof Rigidbody2D ? $ballBody : null;
        }

        $paddleY = $this->transform->position->y;
        $targetY = $this->trackedBall->transform->position->y;
        $ballVelocity = $this->trackedBallBody?->velocity;

        // Anticipate where the ball is heading when moving toward this paddle.
        if ($ballVelocity instanceof Vector3) {
            $isBallComingToAi = $this->isBallTravellingTowardPaddle($ballVelocity->x);
            if ($isBallComingToAi) {
                $xDistance = abs($this->transform->position->x - $this->trackedBall->transform->position->x);
                $travelTime = $xDistance / max(1.0, abs($ballVelocity->x));
                $targetY += $ballVelocity->y * $travelTime;
            }
        }

        // Keep AI beatable by adding bounded tracking error.
        if ($this->aiErrorMargin > 0.0) {
            $targetY += sin(Time::time() * 1.4) * $this->aiErrorMargin;
        }

        $deltaY = $targetY - $paddleY;
        if (abs($deltaY) <= $this->aiDeadZone) {
            $desiredInput = 0.0;
        } else {
            $desiredInput = MathUtil::clamp($deltaY / 6.0, -1.0, 1.0);
        }

        $step = max(0.0, $this->aiReactionRate) * Time::fixedDeltaTime();
        $this->aiSteering = MathUtil::moveTowards($this->aiSteering, $desiredInput, $step);
        $this->applyAiMovement($this->aiSteering);
    }

    private function cacheTrackedBall(): void
    {
        $ball = GameObject::find($this->trackedBallName);
        $this->trackedBall = $ball;
        $ballBody = $ball?->getComponent(Rigidbody2D::class);
        $this->trackedBallBody = $ballBody instanceof Rigidbody2D ? $ballBody : null;
    }

    private function applyAiMovement(float $input): void
    {
        if ($this->body === null) {
            return;
        }

        $this->body->velocity = new Vector3(
            0.0,
            $input * $this->speed * self::SPEED_MULTIPLIER * Time::deltaTime(),
            0.0
        );

        $this->spriteAnimation?->setBool("IsMoving", abs($input) > 0.01);
    }

    private function isBallTravellingTowardPaddle(float $ballVelocityX): bool
    {
        if ($ballVelocityX == 0.0) {
            return false;
        }

        $paddleX = $this->transform->position->x;

        return $paddleX > 0.0
            ? $ballVelocityX > 0.0
            : $ballVelocityX < 0.0;
    }
}
