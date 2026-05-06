<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\Globals;

interface AfterGlobalsWriteHookInterface extends LifecycleHookInterface
{
    public function afterGlobalsWrite(Globals $globals): void;
}
