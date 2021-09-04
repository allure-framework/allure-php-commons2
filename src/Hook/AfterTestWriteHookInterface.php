<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\TestResult;
use Throwable;

interface AfterTestWriteHookInterface extends LifecycleHookInterface
{

    public function afterTestWrite(TestResult $test, ?Throwable $error): void;
}
