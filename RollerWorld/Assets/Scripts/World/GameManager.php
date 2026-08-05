<?php

declare(strict_types=1);

namespace RollerWorld\Game\Scripts\World;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\SerializeField;
use Lenga\Engine\Audio\AudioSource;
use Lenga\Engine\Core\Application;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\Input;
use Lenga\Engine\SceneManagement\Scene;
use Lenga\Engine\UI\Canvas;
use Lenga\Engine\UI\Text;

final class GameManager extends Behaviour
{
    #[Header("UI")]
    #[SerializeField] private ?Canvas $hud = null;
    #[SerializeField] private ?Canvas $pauseMenu = null;

    private int $score = 0;
    private int $totalCollectibles = 0;
    private ?Text $scoreText = null;
    private ?Text $statusText = null;
    private ?AudioSource $pickupAudioSource = null;

    public function start(): void
    {
        $this->pickupAudioSource = $this->gameObject->getComponent(AudioSource::class);
        if (!$this->pickupAudioSource) {
            Debug::log("Warning: No AudioSource component found on GameManager for pickup sound.");
        }
        // Resolve UI
        $this->resolveHud();
        $this->resolvePauseMenu();
        $this->totalCollectibles = $this->countActiveCollectibles();
        $this->syncHud();
    }

    public function update(): void
    {
        if (Input::getButtonDown("Pause")) {
            $this->togglePause();
        }
    }

    public function registerCollectible(int $points = 1): void
    {
        $this->score += $points;
        $this->syncHud();
        $this->pickupAudioSource?->play();
    }

    private function resolveHud(): void
    {
        $canvas = Scene::getActive()?->findCanvas('HUD');
        if (!$canvas instanceof Canvas) {
            return;
        }
        $this->hud = $canvas;

        $scoreText = $this->hud->findTextByName('Score Text');
        $statusText = $this->hud->findTextByName('Status Text');

        $this->scoreText = $scoreText instanceof Text ? $scoreText : null;
        $this->statusText = $statusText instanceof Text ? $statusText : null;
    }

    private function countActiveCollectibles(): int
    {
        $count = 0;
        foreach (GameObject::findGameObjectsWithTag('Collectible') as $collectible) {
            if ($collectible->activeInHierarchy) {
                ++$count;
            }
        }

        return $count;
    }

    private function syncHud(): void
    {
        if ($this->scoreText instanceof Text) {
            $this->scoreText->text = "Score: {$this->score} / {$this->totalCollectibles}";
        }

        if (!$this->statusText instanceof Text) {
            return;
        }

        if ($this->score >= $this->totalCollectibles && $this->totalCollectibles > 0) {
            $this->statusText->visible = true;
            $this->statusText->text = 'All pickups collected!';
            return;
        }

        $this->statusText->visible = true;
        $this->statusText->text = 'Collect every yellow cube.';
    }

    private function resolvePauseMenu(): void
    {
        if (!$this->pauseMenu) {
            $canvas  = Scene::getActive()?->findCanvas('Pause Menu');

            if (!$canvas instanceof Canvas) {
                Debug::warn("Could not resolve PauseMenu");
                return;
            }

            $this->pauseMenu = $canvas;
        }

        $this->pauseMenu->enabled = false;
    }

    private function togglePause(): void
    {
        Application::togglePause();
        $isPaused = Application::isPaused();

        if ($this->pauseMenu) {
            $this->pauseMenu->enabled = $isPaused;
        }
        if ($isPaused) {
            $this->emitEvent('game.paused');
        } else {
            $this->emitEvent('game.resumed');
        }
    }
}
