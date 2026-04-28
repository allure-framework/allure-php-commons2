<?php

declare(strict_types=1);

namespace Qameta\Allure\Model;

use DateTimeImmutable;

interface ResultFactoryInterface
{
    public function createContainer(): ContainerResult;

    public function createTest(): TestResult;

    public function createStep(): StepResult;

    public function createFixture(): FixtureResult;

    public function createAttachment(): AttachmentResult;

    public function createGlobalError(): GlobalError;

    public function createGlobalAttachment(): GlobalAttachment;

    public function createGlobals(): Globals;
}
