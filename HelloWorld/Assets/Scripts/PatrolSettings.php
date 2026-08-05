<?php

declare(strict_types=1);

namespace Sample\HelloWorld\Scripts;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Core\Behaviour;

final class PatrolSettings extends Behaviour
{
    #[Header('Movement')]
    #[Range(0.0, 20.0)]
    public float $speed = 4.0;

    #[Range(0.0, 10.0)]
    public float $pauseTime = 1.0;
}
