<?php

declare(strict_types=1);

namespace RollerWorld\Game\Scripts\World;

use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Attributes\RequireComponent;
use Lenga\Engine\Attributes\Tooltip;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\BoxCollider3D;
use Lenga\Engine\Core\Collision3D;
use Lenga\Engine\Core\CubeRenderer;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Vector3;

#[RequireComponent(CubeRenderer::class)]
#[RequireComponent(BoxCollider3D::class)]
final class Collectible extends Behaviour
{
    #[Range(1, 10)]
    public int $points = 1;

    #[Tooltip('How quickly the pickup rotates so it feels alive in the scene.')]
    #[Range(-240, 240)]
    public float $degreesPerSecond = 90.0;

    #[Tooltip('Optional manager object used to keep score for the Roll-a-Ball scene.')]
    public ?GameObject $managerObject = null;

    private bool $collected = false;

    public function update(): void
    {
        $this->transform->rotate(
            new Vector3(
                0.0,
                $this->degreesPerSecond * Time::deltaTime(),
                0.0,
            ),
            relativeToSelf: false,
        );
    }

    public function onTriggerEnter(Collision3D $collision): void
    {
        if ($this->collected) {
            return;
        }

        $other = $collision->otherGameObject;
        if (!$other instanceof GameObject || !$other->compareTag('Player')) {
            return;
        }

        $this->collected = true;

        $managerObject = $this->managerObject ?? GameObject::find('GameManager');
        $manager = $managerObject?->getComponent(GameManager::class);
        if ($manager instanceof GameManager) {
            $manager->registerCollectible($this->points);
        }

        $this->gameObject->setActive(false);
    }
}
