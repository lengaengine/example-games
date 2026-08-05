<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Level\States;

use Lenga\Engine\Core\Input;
use Lenga\Engine\Core\Time;
use Lenga\Platformer\Scripts\Audio\AudioPreferences;
use Lenga\Platformer\Scripts\Audio\Enumerations\AudioMixerPresetName;
use Lenga\Platformer\Scripts\Level\LevelStateContext;
use Override;

class PlayLevelState extends AbstractLevelState
{

    public function enter(LevelStateContext $context): void
    {
        if ($context->pauseMenu) {
            $context->pauseMenu->enabled = false;
        }
        if ($context->settingsMenu) {
            $context->settingsMenu->enabled = false;
        }

        if ($context->bgmAudioSource) {
            $context->bgmAudioSource->volume = AudioPreferences::getBgmVolume();
            $this->levelManager->mainAudioMixer->transitionToPreset(AudioMixerPresetName::Unpaused->value);
        }

        if ($context->sfxAudioSource) {
            $context->sfxAudioSource->volume = AudioPreferences::getSfxVolume();
        }

        if (Time::isGameplayPaused()) {
            Time::resumeGameplay();
            $this->levelManager->mainAudioMixer?->transitionToPreset(AudioMixerPresetName::Unpaused->value, $this->levelManager->audioPresetTransitionDuration);
        }
    }

    public function exit(LevelStateContext $context): void
    {
        // TODO: Implement exit() method.
    }

    #[Override]
    public function update(LevelStateContext $context): void
    {
        if (Input::getButtonDown("Pause")) {
            $this->levelManager->togglePause();
            $this->setState(new PausedLevelState($this->levelManager));
        }
    }
}
