<?php

namespace Lenga\Platformer\Scripts\Level\States;

use Lenga\Engine\Core\Input;
use Lenga\Platformer\Scripts\Audio\Enumerations\AudioMixerPresetName;
use Lenga\Platformer\Scripts\Level\LevelStateContext;

class PausedLevelState extends AbstractLevelState
{

    public function enter(LevelStateContext $context): void
    {
        $context->pauseMenu->enabled = true;
        $this->levelManager->mainAudioMixer?->transitionToPreset(AudioMixerPresetName::Paused->value, $this->levelManager->audioPresetTransitionDuration);
    }

    public function exit(LevelStateContext $context): void
    {
        $context->pauseMenu->enabled = false;
    }

    public function update(LevelStateContext $context): void
    {
        if (Input::getButtonDown("Cancel")) {
            $this->setState(new PlayLevelState($this->levelManager));
        }
    }
}