<?php

declare(strict_types=1);

namespace Lenga\Pong\Scripts;

use Lenga\Engine\Audio\AudioSource;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Collision2D;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\Random;
use Lenga\Engine\Core\Rigidbody2D;
use Lenga\Engine\Core\Vector3;

final class Ball extends Behaviour
{
    public float $xVelocity = 50.0;
    public float $yVelocity = 25.0;

    private ?Rigidbody2D $rigidBody = null;
    private ?LevelController $levelController = null;
    private ?Vector3 $spawnPosition = null;
    public ?AudioSource $audioSource = null;

    public function awake(): void
    {
        $this->rigidBody = $this->gameObject->getComponent(Rigidbody2D::class);
        $this->spawnPosition = $this->transform->position->clone();

        $manager = GameObject::find('Game Manager');
        $controller = $manager?->getComponent(LevelController::class);
        $this->levelController = $controller instanceof LevelController ? $controller : null;

        $this->audioSource = $this->gameObject->getComponent(AudioSource::class);
    }

    public function resetToSpawn(bool $hide = false): void
    {
        if ($this->spawnPosition instanceof Vector3) {
            $this->transform->position = $this->spawnPosition->clone();
        }

        if ($this->rigidBody instanceof Rigidbody2D) {
            $this->rigidBody->velocity = new Vector3();
        }

        if ($hide) {
            $this->gameObject->setActive(false);
        }
    }

    public function launch(float $speedScale = 1.0): void
    {
        if (!$this->rigidBody instanceof Rigidbody2D) {
            return;
        }

        $this->gameObject->setActive(true);
        $this->resetToSpawn();

        $scaledXVelocity = max(1.0, $this->xVelocity * max(0.0, $speedScale));
        $scaledYVelocity = max(1.0, $this->yVelocity * max(0.0, $speedScale));
        $xVelocity = Random::rangeInt(0, 1) === 0 ? -$scaledXVelocity : $scaledXVelocity;
        $yVelocity = Random::rangeFloat(-$scaledYVelocity, $scaledYVelocity);

        $this->rigidBody->velocity = new Vector3((float) $xVelocity, (float) $yVelocity, 0.0);
    }

    public function onCollisionEnter2D(Collision2D $collision2D): void
    {
        $this->audioSource?->play();
    }

    public function onTriggerEnter2D(Collision2D $collision): void
    {
        $otherName = $collision->otherGameObject?->name ?? '';

        if ($otherName === 'Left Boundary') {
            $this->levelController?->scorePoint(2);
            return;
        }

        if ($otherName === 'Right Boundary') {
            $this->levelController?->scorePoint(1);
        }
    }
}
