<?php

namespace Lenga\Platformer\Scripts\Level;

use Lenga\Engine\Audio\AudioSource;
use Lenga\Engine\UI\Canvas;

final class LevelStateContext
{
    public function __construct(
        public LevelManager $levelManager,
        public ?Canvas      $pauseMenu = null,
        public ?Canvas      $settingsMenu = null,
        public ?AudioSource $bgmAudioSource = null,
        public ?AudioSource $sfxAudioSource = null,
    )
    {
    }
}
