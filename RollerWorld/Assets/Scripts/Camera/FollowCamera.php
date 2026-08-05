<?php

declare(strict_types=1);

namespace RollerWorld\Game\Scripts\Camera;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\Min;
use Lenga\Engine\Attributes\RequireComponent;
use Lenga\Engine\Attributes\Tooltip;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Camera;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Transform;
use Lenga\Engine\Core\Vector3;

#[RequireComponent(Camera::class)]
final class FollowCamera extends Behaviour
{
    #[Header('Target')]
    #[Tooltip('Target transform the camera should follow. If left empty it will look for the Player tag.')]
    public ?Transform $target = null;

    #[Tooltip('Offset from the target in world space. Positive Z stays behind the player with the default facing.')]
    public ?Vector3 $offset = null;

    #[Header('Smoothing')]
    #[Tooltip('How quickly the camera eases into its follow position.')]
    #[Min(0.01)]
    public float $smoothTime = 0.14;

    #[Tooltip('Height above the target to look at so the camera frames more of the scene.')]
    public float $lookAtHeight = 0.85;

    #[Tooltip('When enabled, the camera rotates to look at the target every frame. Disabled by default so fixed-angle follow setups like Roll-a-Ball keep their authored framing.')]
    public bool $lookAtTarget = false;

    #[Tooltip('Optional extra world-space offset applied to the look-at point when Look At Target is enabled.')]
    public ?Vector3 $lookAtOffset = null;

    protected ?Vector3 $currentVelocity = null;

    public function lateUpdate(): void
    {
        if ($this->target === null) {
            $this->target = GameObject::findWithTag('Player')?->transform;
        }

        if ($this->target === null) {
            return;
        }

        $offset = $this->offset ?? new Vector3(0.0, 5.0, 9.0);
        $targetPosition = $this->target->position;
        $desiredPosition = Vector3::sum($targetPosition, $offset);

        $velocity = $this->currentVelocity ?? new Vector3();
        $smoothedPosition = Vector3::smoothDamp(
            $this->transform->position,
            $desiredPosition,
            $velocity,
            $this->smoothTime,
            PHP_FLOAT_MAX,
            Time::deltaTime()
        );
        $this->currentVelocity = $velocity;
        $this->transform->position = $smoothedPosition;

        if ($this->lookAtTarget) {
            $lookAtOffset = $this->lookAtOffset ?? new Vector3(0.0, $this->lookAtHeight, 0.0);
            $this->transform->lookAt(Vector3::sum($targetPosition, $lookAtOffset));
        }
    }
}
