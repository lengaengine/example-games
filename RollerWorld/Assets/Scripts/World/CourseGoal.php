<?php

declare(strict_types=1);

namespace RollerWorld\Game\Scripts\World;

use Lenga\Engine\Attributes\RequireComponent;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\BoxCollider3D;
use Lenga\Engine\Core\Collision3D;
use Lenga\Engine\Core\CubeRenderer;
use Lenga\Engine\Core\GameObject;

#[RequireComponent(CubeRenderer::class)]
#[RequireComponent(BoxCollider3D::class)]
final class CourseGoal extends Behaviour
{
    public ?GameObject $managerObject = null;

    private bool $completed = false;

    public function onTriggerEnter(Collision3D $collision): void
    {
        if ($this->completed) {
            return;
        }

        $other = $collision->otherGameObject;
        if (!$other instanceof GameObject || !$other->compareTag('Player')) {
            return;
        }

        $this->completed = true;

        $renderer = $this->gameObject->getComponent(CubeRenderer::class);
        if ($renderer instanceof CubeRenderer) {
            $renderer->setColor(35, 221, 246, 255);
        }

        $managerObject = $this->managerObject ?? GameObject::find('Course Manager');
        $manager = $managerObject?->getComponent(ObstacleCourseManager::class);
        if ($manager instanceof ObstacleCourseManager) {
            $manager->completeCourse();
        }
    }
}
