<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\FixtureResult;
use Throwable;

interface AfterFixtureStopHookInterface extends LifecycleHookInterface
{

    public function afterFixtureStop(FixtureResult $fixture, ?Throwable $error): void;
}
