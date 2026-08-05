<?php

namespace Lenga\Platformer\Scripts\Menus\Enumerations;

enum MenuButtonType: string
{
    case PLAY  = 'Play Button';
    case SETTINGS = 'Settings Button';
    case QUIT = 'Quit Button';
    case AUDIO_SETTINGS = 'Audio Settings Button';
    case AUDIO_SETTINGS_BACK = 'Audio Settings Back Button';
    case CONTROLLER_SETTINGS = 'Controller Settings Button';
    case CREDITS = 'Credits Button';

    public function getLabel(): string
    {
        return match ($this) {
            self::PLAY => 'Play',
            self::SETTINGS => 'Settings',
            self::QUIT => 'Quit',
            self::AUDIO_SETTINGS => 'Audio',
            self::AUDIO_SETTINGS_BACK => 'Back',
            self::CONTROLLER_SETTINGS => 'Controller',
            self::CREDITS => 'Credits',
        };
    }
}
