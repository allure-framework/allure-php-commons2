<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\TestResult;
use Throwable;

interface AfterTestUpdateHookInterface extends LifecycleHookInterface
{

    public function afterTestUpdate(TestResult $test, ?Throwable $error): void;
}
