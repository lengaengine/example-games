<?php

declare(strict_types=1);

namespace Blasters\Game\Scripts\Level;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Input;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Vector2;
use Lenga\Engine\SceneManagement\Scene;

final class LevelController extends Behaviour
{
    private const array BACKDROP_LAYER_SPEEDS = [
        0 => 0.20,
        1 => 0.45,
        2 => 0.75,
        3 => 1.00,
    ];

    #[Header("Starfield")]
    #[Range(20, 260)]
    public float $starScrollSpeed = 120.0;

    #[Range(0, 1)]
    public float $reverseScrollMultiplier = 0.35;

    #[Range(1, 5)]
    public float $boostScrollMultiplier = 2.6;

    public function update(): void
    {
        $scene = Scene::getActive();
        if (!$scene) {
            return;
        }

        $horizontal = Input::getAxisRaw("Horizontal");
        $isBoosting = Input::getAxisRaw("Boost") > 0.0;

        $scrollMultiplier = 1.0;
        if ($isBoosting) {
            $scrollMultiplier = $this->boostScrollMultiplier;
        } elseif ($horizontal < 0.0) {
            $scrollMultiplier = $this->reverseScrollMultiplier;
        }

        $scrollDistance = $this->starScrollSpeed * $scrollMultiplier * Time::deltaTime();
        if ($scrollDistance <= 0.0) {
            return;
        }

        foreach (self::BACKDROP_LAYER_SPEEDS as $layerIndex => $layerSpeed) {
            $layer = $scene->getBackdropLayer($layerIndex);
            if (!$layer) {
                continue;
            }

            $layer->translateOffset(new Vector2($scrollDistance * $layerSpeed, 0.0));
        }
    }
}
