<?php

namespace Lenga\Platformer\Scripts\Enemy\Enumerations;

enum EnemyState
{
    case Patrol;
    case Chase;
    case Cast;
    case Attack;
    case Dead;
    case Idle;
}
