<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Camera;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\MathUtil;
use Lenga\Engine\Core\Random;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Transform;
use Lenga\Engine\Core\Vector3;

final class CameraFollow extends Behaviour
{
    #[Header("References")]
    public ?Transform $target = null;

    #[Header("Settings")]
    public float $smoothTime = 0.15;
    public float $maxSpeed = PHP_FLOAT_MAX;
    public ?Vector3 $offset = null;
    protected ?Vector3 $currentVelocity = null;
    protected float $shakeRemaining = 0.0;
    protected float $shakeDuration = 0.0;
    protected float $shakeMagnitudeX = 0.0;
    protected float $shakeMagnitudeY = 0.0;

    public function requestShake(float $duration, float $magnitudeX, float $magnitudeY): void
    {
        if ($duration <= 0.0 || ($magnitudeX <= 0.0 && $magnitudeY <= 0.0)) {
            return;
        }

        $this->shakeDuration = max($this->shakeDuration, $duration);
        $this->shakeRemaining = max($this->shakeRemaining, $duration);
        $this->shakeMagnitudeX = max($this->shakeMagnitudeX, $magnitudeX);
        $this->shakeMagnitudeY = max($this->shakeMagnitudeY, $magnitudeY);
    }

    public function lateUpdate(): void
    {
        if ($this->target === null) {
            return;
        }

        $targetPosition = $this->target->position;
        $offset = $this->offset ?? Vector3::zero();
        $newPosition = new Vector3(
            $targetPosition->x + $offset->x,
            $targetPosition->y + $offset->y,
            $targetPosition->z + $offset->z
        );

        $velocity = $this->currentVelocity ?? new Vector3();
        $this->transform->position = Vector3::smoothDamp(
            $this->transform->position,
            $newPosition,
            $velocity,
            $this->smoothTime,
            $this->maxSpeed,
            Time::deltaTime()
        );
        $this->currentVelocity = $velocity;

        if ($this->shakeRemaining > 0.0) {
            $this->shakeRemaining = max(0.0, $this->shakeRemaining - Time::unscaledDeltaTime());
            $falloff = $this->shakeDuration > 0.0
                ? ($this->shakeRemaining / $this->shakeDuration)
                : 0.0;
            $offsetX = $this->nextShakeOffset($this->shakeMagnitudeX * $falloff);
            $offsetY = $this->nextShakeOffset($this->shakeMagnitudeY * $falloff);
            $shaken = $this->transform->position;
            $this->transform->position = new Vector3(
                $shaken->x + $offsetX,
                $shaken->y + $offsetY,
                $shaken->z
            );
        }
    }

    private function nextShakeOffset(float $magnitude): float
    {
        if ($magnitude <= 0.0) {
            return 0.0;
        }

        $sample = Random::rangeInt(-1000, 1000) / 1000.0;
        return $sample * $magnitude;
    }
}
