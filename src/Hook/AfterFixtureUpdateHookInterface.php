<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\FixtureResult;
use Throwable;

interface AfterFixtureUpdateHookInterface extends LifecycleHookInterface
{

    public function afterFixtureUpdate(FixtureResult $fixture, ?Throwable $error): void;
}
