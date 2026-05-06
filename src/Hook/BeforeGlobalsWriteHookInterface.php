<?php

declare(strict_types=1);

namespace Qameta\Allure\Hook;

use Qameta\Allure\Model\Globals;

interface BeforeGlobalsWriteHookInterface extends LifecycleHookInterface
{
    public function beforeGlobalsWrite(Globals $globals): void;
}
