<?php

declare(strict_types=1);

namespace RollerWorld\Game\Scripts\World;

use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Vector3;

final class Turntable extends Behaviour
{
    #[Range(-120, 120)]
    public float $degreesPerSecond = 25.0;

    public function update(): void
    {
        $this->transform->rotate(
            new Vector3(
                0.0,
                $this->degreesPerSecond * Time::deltaTime(),
                0.0,
            ),
            relativeToSelf: false,
        );
    }
}
