<?php

namespace Sample\HelloWorld\Scripts;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\Range;

final class CameraFollowSettings
{
    #[Range(0.01, 2.0)]
    public float $smoothTime = 0.2;

    #[Header('Look Ahead')]
    public float $distance = 2.5;
    public float $verticalOffset = 1.0;
}