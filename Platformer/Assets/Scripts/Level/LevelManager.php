<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Level;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\SerializeField;
use Lenga\Engine\Audio\AudioMixer;
use Lenga\Engine\Audio\AudioSource;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\Time;
use Lenga\Engine\SceneManagement\SceneManager;
use Lenga\Engine\UI\Canvas;
use Lenga\Platformer\Scripts\Level\Interfaces\LevelStateInterface;
use Lenga\Platformer\Scripts\Level\States\PlayLevelState;

final class LevelManager extends Behaviour
{
    private(set) float $audioPresetTransitionDuration = 0.1;

    #[Header("References")]
    public ?Canvas $pauseMenu = null;
    public ?Canvas $settingsMenu = null;
    public ?AudioSource $bgmAudioSource = null;
    public ?AudioSource $sfxAudioSource = null;
    public ?AudioMixer $mainAudioMixer = null;
    private ?LevelStateInterface $state = null;
    private LevelStateContext $context;

    public function start(): void
    {
        if (!$this->pauseMenu) {
            $this->pauseMenu = SceneManager::getActiveScene()->findCanvas("Pause Menu");
        }
        if (!$this->settingsMenu) {
            $this->settingsMenu = SceneManager::getActiveScene()->findCanvas("Settings Menu");
        }

        if (!$this->bgmAudioSource) {
            $this->bgmAudioSource = $this->gameObject->getComponents(AudioSource::class)[0] ?? null;
        }
        if (!$this->sfxAudioSource) {
            $this->sfxAudioSource = $this->gameObject->getComponents(AudioSource::class)[1] ?? null;
        }
        $this->context = new LevelStateContext(
            $this,
            $this->pauseMenu,
            $this->settingsMenu,
            $this->bgmAudioSource,
            $this->sfxAudioSource
        );
        $this->setState(new PlayLevelState($this));
    }

    public function update(): void
    {
        $this->state->update($this->context);
    }

    public function togglePause(): void
    {
        Time::toggleGameplayPause();

        if ($this->pauseMenu) {
            $this->pauseMenu->enabled = Time::isGameplayPaused();
        }
    }

    public function resumeGame(): void
    {
        if (!Time::isGameplayPaused()) {
            return;
        }

        Time::resumeGameplay();

        if ($this->pauseMenu) {
            $this->pauseMenu->enabled = false;
        }
    }

    public function openSettingsMenu(): void
    {
        Debug::log("Opening settings menu...");
    }

    public function returnToMainMenu(): void
    {
        SceneManager::loadScene("Main Menu");
    }

    public function setState(Interfaces\LevelStateInterface $state): void
    {
        $this->state?->exit($this->context);
        $this->state = $state;
        $this->state->enter($this->context);
    }
}
