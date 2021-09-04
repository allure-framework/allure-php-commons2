<?php

declare(strict_types=1);

namespace Qameta\Allure\Test;

use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Qameta\Allure\AllureLifecycle;
use Qameta\Allure\Exception\ActiveContainerNotFoundException;
use Qameta\Allure\Internal\HooksNotifierInterface;
use Qameta\Allure\Internal\ResultStorageInterface;
use Qameta\Allure\Io\ClockInterface;
use Qameta\Allure\Io\ResultsWriterInterface;
use Qameta\Allure\Model\ContainerResult;
use Qameta\Allure\Model\StorableResultInterface;
use Throwable;

/**
 * @covers \Qameta\Allure\AllureLifecycle
 */
class AllureLifecycleTest extends TestCase
{

    public function testStartContainer_NoExceptionsThrown_NotifiesHooksWithoutError(): void
    {
        $hooksNotifier = $this->createMock(HooksNotifierInterface::class);
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $hooksNotifier,
            $this->createStub(ResultStorageInterface::class),
        );

        $container = new ContainerResult('a');
        $hooksNotifier
            ->expects(self::once())
            ->method('beforeContainerStart')
            ->id('before')
            ->with(self::identicalTo($container));
        $hooksNotifier
            ->expects(self::once())
            ->after('before')
            ->method('afterContainerStart')
            ->with(self::identicalTo($container), self::identicalTo(null));
        $lifecycle->startContainer($container);
    }

    public function testStartContainer_NoExceptionsThrown_NeverLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStub(ResultStorageInterface::class),
        );

        $logger
            ->expects(self::never())
            ->method('error');
        $lifecycle->startContainer(new ContainerResult('a'));
    }

    public function testStartContainer_StorageFailsToSetContainer_NotifiesHooksWithError(): void
    {
        $hooksNotifier = $this->createMock(HooksNotifierInterface::class);
        $error = new Exception();
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $hooksNotifier,
            $this->createNonSettableStorage($container, $error),
        );

        $hooksNotifier
            ->expects(self::once())
            ->id('before')
            ->method('beforeContainerStart')
            ->with(self::identicalTo($container));
        $hooksNotifier
            ->expects(self::once())
            ->after('before')
            ->method('afterContainerStart')
            ->with(self::identicalTo($container), self::identicalTo($error));
        $lifecycle->startContainer($container);
    }

    public function testStartContainer_StorageFailsToSetContainer_LogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $error = new Exception();
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createNonSettableStorage($container, $error),
        );

        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Container (UUID: {uuid}) not started'),
                self::identicalTo(['uuid' => 'a', 'exception' => $error]),
            );
        $lifecycle->startContainer($container);
    }

    public function testStartContainer_ClockProvidesTime_ContainerStartIsSetToSameTime(): void
    {
        $time = new DateTimeImmutable('@0');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock($time),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStub(ResultStorageInterface::class),
        );

        $container = new ContainerResult('a');
        $lifecycle->startContainer($container);
        self::assertSame($time, $container->getStart());
    }

    public function testStartContainer_GivenContainer_SetsSameContainerInStorage(): void
    {
        $resultStorage = $this->createMock(ResultStorageInterface::class);
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $resultStorage,
        );

        $container = new ContainerResult('a');
        $resultStorage
            ->expects(self::once())
            ->method('set')
            ->with(self::identicalTo($container));
        $lifecycle->startContainer($container);
    }

    public function testUpdateContainer_ContainerNotGivenNorStarted_LogsErrorAndReturnsNull(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStub(ResultStorageInterface::class),
        );

        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Container (UUID: {uuid}) not updated'),
                self::equalTo(['uuid' => null, 'exception' => new ActiveContainerNotFoundException()]),
            );
        self::assertNull($lifecycle->updateContainer(fn () => null));
    }

    public function testUpdateContainer_StorageFailsToProvideGivenContainer_LogsErrorAndReturnsNull(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $error = new Exception();
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithoutContainer('a', $error),
        );

        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Container (UUID: {uuid}) not updated'),
                self::equalTo(['uuid' => 'a', 'exception' => $error]),
            );
        self::assertNull($lifecycle->updateContainer(fn () => null, 'a'));
    }

    public function testUpdateContainer_StorageFailsToProvideStartedContainer_LogsErrorAndReturnsNull(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $error = new Exception();
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithoutContainer('a', $error),
        );

        $lifecycle->startContainer(new ContainerResult('a'));

        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Container (UUID: {uuid}) not updated'),
                self::equalTo(['uuid' => 'a', 'exception' => $error]),
            );
        self::assertNull($lifecycle->updateContainer(fn () => null));
    }

    public function testUpdateContainer_ContainerNotGivenButStarted_NeverLogsErrorAndReturnsUuid(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
        );

        $lifecycle->startContainer($container);

        $logger
            ->expects(self::never())
            ->method('error');
        self::assertSame('a', $lifecycle->updateContainer(fn () => null));
    }

    public function testUpdateContainer_StorageProvidesGivenContainer_NeverLogsErrorAndReturnsUuid(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer(new ContainerResult('a')),
        );

        $logger
            ->expects(self::never())
            ->method('error');
        self::assertSame('a', $lifecycle->updateContainer(fn () => null, 'a'));
    }

    public function testUpdateContainer_CallbackNeverThrowsException_NotifiesHooksWithoutError(): void
    {
        $hooksNotifier = $this->createMock(HooksNotifierInterface::class);
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $hooksNotifier,
            $this->createStorageWithContainer($container),
        );

        $hooksNotifier
            ->expects(self::once())
            ->id('before')
            ->method('beforeContainerUpdate')
            ->with(self::identicalTo($container));
        $hooksNotifier
            ->expects(self::once())
            ->after('before')
            ->method('afterContainerUpdate')
            ->with(self::identicalTo($container), self::identicalTo(null));
        $lifecycle->updateContainer(fn () => null, 'a');
    }

    public function testUpdateContainer_CallbackThrowsException_NotifiesHooksWithError(): void
    {
        $hooksNotifier = $this->createMock(HooksNotifierInterface::class);
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $hooksNotifier,
            $this->createStorageWithContainer($container),
        );
        $error = new Exception();
        $hooksNotifier
            ->expects(self::once())
            ->id('before')
            ->method('beforeContainerUpdate')
            ->with(self::identicalTo($container));
        $hooksNotifier
            ->expects(self::once())
            ->after('before')
            ->method('afterContainerUpdate')
            ->with(self::identicalTo($container), self::identicalTo($error));
        $lifecycle->updateContainer(fn () => throw $error, 'a');
    }

    public function testUpdateContainer_CallbackThrowsException_LogsErrorAndReturnsUuid(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
        );
        $error = new Exception();
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Container (UUID: {uuid}) not updated'),
                self::identicalTo(['uuid' => 'a', 'exception' => $error]),
            );
        self::assertSame('a', $lifecycle->updateContainer(fn () => throw $error, 'a'));
    }

    public function testStopContainer_ContainerNotGivenNorStarted_LogsErrorAndReturnsNull(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStub(ResultStorageInterface::class),
        );

        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Container (UUID: {uuid}) not stopped'),
                self::equalTo(['uuid' => null, 'exception' => new ActiveContainerNotFoundException()]),
            );
        self::assertNull($lifecycle->stopContainer());
    }

    public function testStopContainer_StorageFailsToProvideGivenContainer_LogsErrorAndReturnsNull(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $error = new Exception();
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithoutContainer('a', $error),
        );

        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Container (UUID: {uuid}) not stopped'),
                self::equalTo(['uuid' => 'a', 'exception' => $error]),
            );
        self::assertNull($lifecycle->stopContainer('a'));
    }

    public function testStopContainer_StorageFailsToProvideStartedContainer_LogsErrorAndReturnsNull(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $error = new Exception();
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithoutContainer('a', $error),
        );

        $lifecycle->startContainer(new ContainerResult('a'));

        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Container (UUID: {uuid}) not stopped'),
                self::equalTo(['uuid' => 'a', 'exception' => $error]),
            );
        self::assertNull($lifecycle->stopContainer());
    }

    public function testStopContainer_ContainerNotProvidedButStarted_NeverLogsErrorAndReturnsUuid(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
        );

        $lifecycle->startContainer($container);
        $logger
            ->expects(self::never())
            ->method('error');
        self::assertSame('a', $lifecycle->stopContainer());
    }

    public function testStopContainer_StorageProvidesGivenContainer_NeverLogsErrorAndReturnsUuid(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer(new ContainerResult('a')),
        );
        $logger
            ->expects(self::never())
            ->method('error');
        self::assertSame('a', $lifecycle->stopContainer('a'));
    }

    public function testStopContainer_ClockProvidesTime_ContainerHasSameStop(): void
    {
        $time = new DateTimeImmutable('@0');
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock($time),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
        );

        $lifecycle->stopContainer('a');
        self::assertSame($time, $container->getStop());
    }

    public function testStopContainer_ClockNeverThrowsException_NotifiesHooksWithoutError(): void
    {
        $hooksNotifier = $this->createMock(HooksNotifierInterface::class);
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $hooksNotifier,
            $this->createStorageWithContainer($container),
        );

        $hooksNotifier
            ->expects(self::once())
            ->id('before')
            ->method('beforeContainerStop')
            ->with(self::identicalTo($container));
        $hooksNotifier
            ->expects(self::once())
            ->after('before')
            ->method('afterContainerStop')
            ->with(self::identicalTo($container), self::identicalTo(null));
        $lifecycle->stopContainer('a');
    }

    public function testStopContainer_ClockThrowsException_NotifiesHooksWithError(): void
    {
        $hooksNotifier = $this->createMock(HooksNotifierInterface::class);
        $error = new Exception();
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createFailingClock($error),
            $this->createStub(ResultsWriterInterface::class),
            $hooksNotifier,
            $this->createStorageWithContainer($container),
        );
        $hooksNotifier
            ->expects(self::once())
            ->id('before')
            ->method('beforeContainerStop')
            ->with(self::identicalTo($container));
        $hooksNotifier
            ->expects(self::once())
            ->after('before')
            ->method('afterContainerStop')
            ->with(self::identicalTo($container), self::identicalTo($error));
        $lifecycle->stopContainer('a');
    }

    private function createStorageWithContainer(ContainerResult $container): ResultStorageInterface
    {
        $resultStorage = $this->createMock(ResultStorageInterface::class);
        $resultStorage
            ->method('getContainer')
            ->with(self::identicalTo($container->getUuid()))
            ->willReturn($container);

        return $resultStorage;
    }

    private function createStorageWithoutContainer(string $uuid, Throwable $error): ResultStorageInterface
    {
        $resultStorage = $this->createMock(ResultStorageInterface::class);
        $resultStorage
            ->method('getContainer')
            ->with(self::identicalTo($uuid))
            ->willThrowException($error);

        return $resultStorage;
    }

    private function createNonSettableStorage(StorableResultInterface $result, Throwable $error): ResultStorageInterface
    {
        $resultStorage = $this->createMock(ResultStorageInterface::class);
        $resultStorage
            ->method('set')
            ->with(self::identicalTo($result))
            ->willThrowException($error);

        return $resultStorage;
    }

    private function createClock(?DateTimeImmutable $time = null): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock
            ->method('now')
            ->willReturn($time ?? new DateTimeImmutable('@0'));

        return $clock;
    }

    private function createFailingClock(Throwable $error): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock
            ->method('now')
            ->willThrowException($error);

        return $clock;
    }
}
