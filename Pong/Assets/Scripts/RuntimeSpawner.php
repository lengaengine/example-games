<?php

declare(strict_types=1);

namespace My\App\Scripts;

use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\RectangleRenderer;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Vector3;

final class RuntimeSpawner extends Behaviour
{
    private ?GameObject $spawned = null;

    public function start(): void
    {
        $scene = $this->gameObject->getScene();
        $this->spawned = $scene?->createGameObject('Spawned Block') ?? GameObject::create('Spawned Block');
        $this->spawned->transform->position = new Vector3(260.0, 180.0, 0.0);

        /** @var RectangleRenderer $renderer */
        $renderer = $this->spawned->addComponent(RectangleRenderer::class);
        $renderer->setSize(40.0, 40.0);
        $renderer->setColor(255, 120, 80);
        $renderer->sortingLayer = 'Foreground';
    }

    public function update(): void
    {
        if ($this->spawned === null) {
            return;
        }

        $offset = \sin(Time::time() * 3.0) * 30.0;
        $this->spawned->transform->position = new Vector3(260.0 + $offset, 180.0, 0.0);
    }
}
