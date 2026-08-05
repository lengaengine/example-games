<?php

namespace Lenga\Platformer\Scripts\Level\Interfaces;

use Lenga\Platformer\Scripts\Level\LevelStateContext;

interface LevelStateInterface
{
    public function enter(LevelStateContext $context): void;
    public function exit(LevelStateContext $context): void;

    public function setState(LevelStateInterface $state): void;

    public function update(LevelStateContext $context): void;
}