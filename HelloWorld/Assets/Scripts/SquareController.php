<?php

declare(strict_types=1);

namespace Sample\HelloWorld\Scripts;

use Lenga\Engine\Core\Application;
use Lenga\Engine\Enumerations\KeyCode;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\Input;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Vector3;

class SquareController extends Behaviour
{
    const string TAG = '[PlayerController]';

    /**
     * Pixels per second in the X direction.
     */
    private float $speed = 5.0;
    #[SerializeField]
    private float $deadZone = 0.1;

    public function awake(): void
    {
        echo self::TAG . " awake()" . PHP_EOL;
    }

    public function onEnable(): void
    {
        echo self::TAG . " onEnable()" . PHP_EOL;
    }

    public function start(): void
    {
        echo self::TAG . " start()" . PHP_EOL;
    }

    public function fixedUpdate(): void
    {
        // echo self::TAG . " fixedUpdate()" . PHP_EOL;
    }

    public function update(): void
    {
        $h = Input::getAxis("Horizontal");
        $v = Input::getAxis("Vertical");

        if (abs($h) > $this->deadZone || abs($v) > $this->deadZone) {
            $this->move($h, $v);
        }

        if (Input::getKeyDown(KeyCode::ESCAPE)) {
            Application::quit();
        }
    }

    public function lateUpdate(): void
    {
        // echo self::TAG . " lateUpdate()" . PHP_EOL;
    }

    public function onDisable(): void
    {
        echo self::TAG . " onDisable()" . PHP_EOL;
    }

    public function onDestroy(): void
    {
        echo self::TAG . " onDestroy()" . PHP_EOL;
    }

    public function move(float $h, float $v): void
    {
        $movement = new Vector3(
            $h * $this->speed * Time::deltaTime(),
            $v * $this->speed * Time::deltaTime(),
            0.0,
        );

        $this->transform->translate($movement);
    }
}
