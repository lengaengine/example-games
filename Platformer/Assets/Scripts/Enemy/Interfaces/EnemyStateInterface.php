<?php

namespace Lenga\Platformer\Scripts\Enemy\Interfaces;

interface EnemyStateInterface
{
    public function enter(EnemyStateContextInterface $context): void;

    public function exit(EnemyStateContextInterface $context): void;

    public function setState(EnemyStateInterface $state): void;

    public function update(EnemyStateContextInterface $context): void;
}