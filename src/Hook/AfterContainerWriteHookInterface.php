<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\ContainerResult;
use Throwable;

interface AfterContainerWriteHookInterface extends LifecycleHookInterface
{

    public function afterContainerWrite(ContainerResult $container, ?Throwable $error): void;
}
