<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\TestResult;
use Throwable;

interface AfterTestStopHookInterface extends LifecycleHookInterface
{

    public function afterTestStop(TestResult $test, ?Throwable $error): void;
}
