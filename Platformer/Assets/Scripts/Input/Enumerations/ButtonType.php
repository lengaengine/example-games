<?php

namespace Lenga\Platformer\Scripts\Input\Enumerations;

enum ButtonType: string
{
    case JUMP = "Jump";
    case PAUSE = "Pause";
    case LIGHT_ATTACK = "Light Attack";
    case HEAVY_ATTACK = "Heavy Attack";
    case DODGE = "Dodge";
    case DASH = "Dash";
    case CONFIRM = "Confirm";
    case CANCEL = "Cancel";
}
