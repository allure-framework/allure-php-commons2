<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\TestResult;
use Throwable;

interface AfterTestScheduleHookInterface extends LifecycleHookInterface
{

    public function afterTestSchedule(TestResult $test, ?Throwable $error): void;
}
