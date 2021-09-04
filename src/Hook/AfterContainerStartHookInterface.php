<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\ContainerResult;
use Throwable;

interface AfterContainerStartHookInterface extends LifecycleHookInterface
{

    public function afterContainerStart(ContainerResult $container, ?Throwable $error): void;
}
