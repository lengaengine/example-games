<?php

declare(strict_types=1);

namespace Blasters\Game\Scripts\Player;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Attributes\SerializeField;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Input;
use Lenga\Engine\Core\Quaternion;
use Lenga\Engine\Core\Rigidbody2D;
use Lenga\Engine\Core\SpriteAnimation;
use Lenga\Engine\Core\Transform;
use Lenga\Engine\Core\Vector2;
use Lenga\Engine\Core\Vector3;

final class PlayerController extends Behaviour
{
    #[Header("Movement")]
    #[Range(5, 20)]
    public float $speed = 5.0;
    #[SerializeField] private ?Vector2 $direction = null;

    #[Range(-45, 45)]
    public float $rollAngle = 30.0;
    
    #[Header("Bounds")]
    public ?Vector2 $min = null;
    public ?Vector2 $max = null;

    private ?Vector3 $minBoundary = null;
    private ?Vector3 $maxBoundary = null;

    #[Header("Input")]
    public float $deadZone = 0.1;

    #[Header("Weapons")]
    public ?Transform $spawnPoint = null;

    private ?Rigidbody2D $body = null;

    private ?SpriteAnimation $animation = null;
    
    public function awake(): void
    {
        if (!$this->min) {
            $this->min = Vector2::zero();
        }
        
        if (!$this->max) {
            $this->max = new Vector2(720, 1280);
        }
    }

    public function start(): void
    {
        $this->body = $this->gameObject->getComponent(Rigidbody2D::class);
        $this->animation = $this->gameObject->getComponent(SpriteAnimation::class);

        $this->minBoundary = new Vector3($this->min->x, $this->min->y, 0);
        $this->maxBoundary = new Vector3($this->max->x, $this->max->y, 0);
    }

    public function update(): void
    {
        $h = Input::getAxisRaw("Horizontal");
        $v = Input::getAxisRaw("Vertical");
        $isBoosting = Input::getAxisRaw("Boost") > 0.0;
        $this->direction = new Vector2($h, $v)->normalized;

        // TODO: Roll the ship along it's X-Axis
        $this->transform->rotation = Quaternion::fromEulerAngles(new Vector3($this->rollAngle * $v, 0 , 0));

        if ($this->animation) {
            $this->animation->setFloat("HorizontalDirection", $h);
            $this->animation->setFloat("VerticalDirection", $v);
            $this->animation->setBool("IsBoosting", $isBoosting);
        }
    }

    public function fixedUpdate(): void
    {
        $velocity = new Vector3(
            $this->direction->x * $this->speed,
            $this->direction->y * $this->speed,
            0
        );

        $newPosition = Vector3::sum($this->transform->position, $velocity);

        if ($newPosition->x < $this->minBoundary->x || $newPosition->x > $this->maxBoundary->x) {
            $newPosition->x = 0;
        }
        if ($newPosition->y < $this->minBoundary->y || $newPosition->y > $this->maxBoundary->y) {
            $newPosition->y = 0;
        }

        $this->body->velocity = $velocity;
    }
}
