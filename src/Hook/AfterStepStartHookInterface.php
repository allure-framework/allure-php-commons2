<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\StepResult;
use Throwable;

interface AfterStepStartHookInterface extends LifecycleHookInterface
{

    public function afterStepStart(StepResult $step, ?Throwable $error): void;
}
