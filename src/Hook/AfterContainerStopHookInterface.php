<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\ContainerResult;
use Throwable;

interface AfterContainerStopHookInterface extends LifecycleHookInterface
{

    public function afterContainerStop(ContainerResult $container, ?Throwable $error): void;
}
