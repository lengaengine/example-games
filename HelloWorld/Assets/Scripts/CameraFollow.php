<?php

declare(strict_types=1);

namespace Sample\HelloWorld\Scripts;

use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Attributes\SerializeReference;

final class CameraFollow extends Behaviour
{
    #[SerializeReference]
    public CameraFollowSettings $settings;
}
