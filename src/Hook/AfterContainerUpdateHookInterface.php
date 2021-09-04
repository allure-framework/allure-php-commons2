<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\ContainerResult;
use Throwable;

interface AfterContainerUpdateHookInterface extends LifecycleHookInterface
{

    public function afterContainerUpdate(ContainerResult $container, ?Throwable $error): void;
}
