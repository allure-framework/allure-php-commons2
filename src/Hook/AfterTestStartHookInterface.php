<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\TestResult;
use Throwable;

interface AfterTestStartHookInterface extends LifecycleHookInterface
{

    public function afterTestStart(TestResult $test, ?Throwable $error): void;
}
