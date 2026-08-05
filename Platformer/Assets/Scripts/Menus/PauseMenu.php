<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Menus;

use Lenga\Engine\Attributes\AddComponentMenu;
use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\RequireComponent;
use Lenga\Engine\Attributes\SerializeField;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Debug;
use Lenga\Platformer\Scripts\Level\LevelManager;

#[AddComponentMenu("Menus/PauseMenu")]
#[RequireComponent(LevelManager::class)]
final class PauseMenu extends Behaviour
{
    #[Header("References")]
    #[SerializeField] private ?LevelManager $levelManager = null;

    public function start(): void
    {
        if (!$this->levelManager) {
            $this->levelManager = $this->gameObject->getComponent(LevelManager::class);

            if (!$this->levelManager) {
                Debug::warn('PauseMenu requires a reference to LevelManager.');
            }
        }
    }

    public function resumeGame(): void
    {
        $this->levelManager->resumeGame();
    }
}
