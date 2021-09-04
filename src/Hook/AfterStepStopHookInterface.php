<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\StepResult;
use Throwable;

interface AfterStepStopHookInterface extends LifecycleHookInterface
{

    public function afterStepStop(StepResult $step, ?Throwable $error): void;
}
