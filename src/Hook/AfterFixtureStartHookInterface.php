<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\FixtureResult;
use Throwable;

interface AfterFixtureStartHookInterface extends LifecycleHookInterface
{

    public function afterFixtureStart(FixtureResult $fixture, ?Throwable $error): void;
}
