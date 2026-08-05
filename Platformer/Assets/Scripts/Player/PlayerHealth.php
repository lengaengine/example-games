<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Player;

use Lenga\Engine\Attributes\AddComponentMenu;
use Lenga\Engine\Attributes\Tooltip;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\Time;
use Lenga\Engine\SceneManagement\Scene;
use Lenga\Engine\SceneManagement\SceneManager;
use Lenga\Engine\UI\Image;
use Lenga\Platformer\Scripts\Combat\Damageable;

#[AddComponentMenu("Player/Player Health")]
final class PlayerHealth extends Behaviour
{
    #[Tooltip("Canvas used to display the heart UI.")]
    public string $hudCanvasName = 'HUD';

    #[Tooltip("Reload the current scene after the player dies.")]
    public bool $reloadSceneOnDeath = true;

    #[Tooltip("Delay before the current scene reloads after death.")]
    public float $reloadDelay = 1.0;

    private ?Damageable $damageable = null;
    /** @var list<Image> */
    private array $heartImages = [];
    private int $lastShownHealth = -1;
    private float $reloadAt = -1.0;

    public function start(): void
    {
        $this->damageable = $this->gameObject->getComponent(Damageable::class);
        if (!$this->damageable instanceof Damageable) {
            Debug::warn('PlayerHealth requires a Damageable component.');
            return;
        }

        $hud = Scene::getActive()?->findCanvas($this->hudCanvasName);
        if ($hud !== null) {
            foreach (['Heart 1', 'Heart 2', 'Heart 3'] as $heartName) {
                $element = $hud->findElementByName($heartName);
                if ($element instanceof Image) {
                    $this->heartImages[] = $element;
                }
            }
        }

        $this->syncHearts();
    }

    public function update(): void
    {
        if (!$this->damageable instanceof Damageable) {
            return;
        }

        $this->syncHearts();

        if ($this->damageable->isDead && $this->reloadSceneOnDeath) {
            if ($this->reloadAt < 0.0) {
                $this->reloadAt = Time::time() + $this->reloadDelay;
            }

            if (Time::time() >= $this->reloadAt) {
                $activeScene = SceneManager::getActiveScene();
                if ($activeScene !== null) {
                    SceneManager::loadScene($activeScene->name);
                }
            }
        }
    }

    private function syncHearts(): void
    {
        if (!$this->damageable instanceof Damageable) {
            return;
        }

        $currentHealth = $this->damageable->currentHealth;
        if ($currentHealth === $this->lastShownHealth) {
            return;
        }

        foreach ($this->heartImages as $index => $heart) {
            $heart->visible = $index < $currentHealth;
        }

        $this->lastShownHealth = $currentHealth;
    }
}
