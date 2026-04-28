<?php

declare(strict_types=1);

namespace Qameta\Allure\Test\Model;

use PHPUnit\Framework\TestCase;
use Qameta\Allure\Model\ResultFactory;
use Qameta\Allure\Io\ClockInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactoryInterface;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * @covers \Qameta\Allure\Model\ResultFactory
 */
class ResultFactoryTest extends TestCase
{
    public function testCreateContainer_FactoryProvidesUuid_ResultHasSameUuid(): void
    {
        $uuidFactory = $this->createStub(UuidFactoryInterface::class);
        $uuid = Uuid::fromString('00000000-0000-0000-0000-000000000001');
        $uuidFactory
            ->method('uuid4')
            ->willReturn($uuid);
        $clock = $this->createStub(ClockInterface::class);

        $resultFactory = new ResultFactory($uuidFactory, $clock);
        self::assertSame(
            '00000000-0000-0000-0000-000000000001',
            $resultFactory->createContainer()->getUuid(),
        );
    }

    public function testCreateTest_FactoryProvidesUuid_ResultHasSameUuid(): void
    {
        $uuidFactory = $this->createStub(UuidFactoryInterface::class);
        $uuid = Uuid::fromString('00000000-0000-0000-0000-000000000001');
        $uuidFactory
            ->method('uuid4')
            ->willReturn($uuid);
        $clock = $this->createStub(ClockInterface::class);

        $resultFactory = new ResultFactory($uuidFactory, $clock);
        self::assertSame(
            '00000000-0000-0000-0000-000000000001',
            $resultFactory->createTest()->getUuid(),
        );
    }

    public function testCreateStep_FactoryProvidesUuid_ResultHasSameUuid(): void
    {
        $uuidFactory = $this->createStub(UuidFactoryInterface::class);
        $uuid = Uuid::fromString('00000000-0000-0000-0000-000000000001');
        $uuidFactory
            ->method('uuid4')
            ->willReturn($uuid);
        $clock = $this->createStub(ClockInterface::class);

        $resultFactory = new ResultFactory($uuidFactory, $clock);
        self::assertSame(
            '00000000-0000-0000-0000-000000000001',
            $resultFactory->createStep()->getUuid(),
        );
    }

    public function testCreateFixture_FactoryProvidesUuid_ResultHasSameUuid(): void
    {
        $uuidFactory = $this->createStub(UuidFactoryInterface::class);
        $uuid = Uuid::fromString('00000000-0000-0000-0000-000000000001');
        $uuidFactory
            ->method('uuid4')
            ->willReturn($uuid);
        $clock = $this->createStub(ClockInterface::class);

        $resultFactory = new ResultFactory($uuidFactory, $clock);
        self::assertSame(
            '00000000-0000-0000-0000-000000000001',
            $resultFactory->createFixture()->getUuid(),
        );
    }

    public function testCreateAttachment_FactoryProvidesUuid_ResultHasSameUuid(): void
    {
        $uuidFactory = $this->createStub(UuidFactoryInterface::class);
        $uuid = Uuid::fromString('00000000-0000-0000-0000-000000000001');
        $uuidFactory
            ->method('uuid4')
            ->willReturn($uuid);
        $clock = $this->createStub(ClockInterface::class);

        $resultFactory = new ResultFactory($uuidFactory, $clock);
        self::assertSame(
            '00000000-0000-0000-0000-000000000001',
            $resultFactory->createAttachment()->getUuid(),
        );
    }

    public function testCreateGlobalError_FactoryProvidesTimestamp_HasTimestamp(): void
    {
        $uuidFactory = $this->createStub(UuidFactoryInterface::class);
        $uuid = Uuid::fromString("00000000-0000-0000-0000-000000000001");
        $uuidFactory
            ->method("uuid4")
            ->willReturn($uuid);
        $clock = $this->createStub(ClockInterface::class);
        $date = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ISO8601,
            "2020-01-01T10:20:31+00:00",
        );
        $clock
            ->method("now")
            ->willReturn($date);

        $resultFactory = new ResultFactory($uuidFactory, $clock);

        $globalError = $resultFactory->createGlobalError();

        self::assertEquals($date, $globalError->getTimestamp());
    }

    public function testCreateGlobalAttachment_FactoryProvidesUuidAndTimestamp_HasUuidAndTimestamp(): void
    {
        $uuidFactory = $this->createStub(UuidFactoryInterface::class);
        $uuid = Uuid::fromString("00000000-0000-0000-0000-000000000001");
        $uuidFactory
            ->method("uuid4")
            ->willReturn($uuid);
        $clock = $this->createStub(ClockInterface::class);
        $date = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ISO8601,
            "2020-01-01T10:20:31+00:00",
        );
        $clock
            ->method("now")
            ->willReturn($date);

        $resultFactory = new ResultFactory($uuidFactory, $clock);

        $globalAttachment = $resultFactory->createGlobalAttachment();


        self::assertEquals("00000000-0000-0000-0000-000000000001", $globalAttachment->getUuid());
        self::assertEquals($date, $globalAttachment->getTimestamp());
    }

    public function testCreateGlobals_FactoryProvidesUuid_ResultHasSameUuid(): void
    {
        $uuidFactory = $this->createStub(UuidFactoryInterface::class);
        $uuid = Uuid::fromString("00000000-0000-0000-0000-000000000001");
        $uuidFactory
            ->method("uuid4")
            ->willReturn($uuid);
        $clock = $this->createStub(ClockInterface::class);
        $resultFactory = new ResultFactory($uuidFactory, $clock);

        $globals = $resultFactory->createGlobals();


        self::assertEquals("00000000-0000-0000-0000-000000000001", $globals->getUuid());
    }
}
