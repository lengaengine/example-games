<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Level;

use Lenga\Engine\Attributes\AddComponentMenu;
use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\SerializeField;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Transform;
use Lenga\Engine\Core\Vector2;
use Lenga\Engine\Core\Vector3;

#[AddComponentMenu("Level/Platform")]
final class Platform extends Behaviour
{
    #[Header("References")]
    #[SerializeField] private ?Transform $startingPoint = null;
    #[SerializeField] private ?Transform $finishingPoint = null;

    #[Header("Movement")]
    public float $moveSpeed = 10.0;
    private Vector3 $targetPosition;

    #[Header("Debug")]
    public bool $debugMode = false;

    public function start(): void
    {
        $this->targetPosition = $this->finishingPoint?->position ?? $this->transform->position;
    }

    public function update(): void
    {
        $maxDelta = $this->moveSpeed * Time::deltaTime();
        $this->transform->position = Vector3::moveTowards($this->startingPoint->position, $this->targetPosition, $maxDelta);
        if ($this->debugMode) {
            Debug::log("Max Delta: " . $maxDelta);
        }

        if ($this->transform->position->equals($this->startingPoint->position)) {
            $this->targetPosition = $this->finishingPoint?->position ?? $this->transform->position;
        } elseif ($this->transform->position->equals($this->finishingPoint->position)) {
            $this->targetPosition = $this->startingPoint?->position ?? $this->transform->position;
        }
    }
}
