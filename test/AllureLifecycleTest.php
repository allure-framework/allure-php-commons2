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
use Qameta\Allure\Internal\ThreadContext;
use Qameta\Allure\Internal\ThreadContextInterface;
use Qameta\Allure\Io\ClockInterface;
use Qameta\Allure\Io\ResultsWriterInterface;
use Qameta\Allure\Model\AttachmentResult;
use Qameta\Allure\Model\ContainerResult;
use Qameta\Allure\Model\FixtureResult;
use Qameta\Allure\Model\Stage;
use Qameta\Allure\Model\StepResult;
use Qameta\Allure\Model\StorableResultInterface;
use Qameta\Allure\Model\TestResult;
use Throwable;

/**
 * @covers \Qameta\Allure\AllureLifecycle
 */
class AllureLifecycleTest extends TestCase
{

    public function testStartContainer_NoExceptionsThrownDuringStart_NotifiesHooksWithoutError(): void
    {
        $hooksNotifier = $this->createMock(HooksNotifierInterface::class);
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $hooksNotifier,
            $this->createStub(ResultStorageInterface::class),
            new ThreadContext(),
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

    public function testStartContainer_NoExceptionsThrownDuringStart_NeverLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStub(ResultStorageInterface::class),
            new ThreadContext(),
        );

        $logger
            ->expects(self::never())
            ->method('error');
        $lifecycle->startContainer(new ContainerResult('a'));
    }

    public function testStartContainer_ExceptionThrownDuringStart_NotifiesHooksWithError(): void
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
            new ThreadContext(),
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
            new ThreadContext(),
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
            new ThreadContext(),
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
            new ThreadContext(),
        );

        $container = new ContainerResult('a');
        $resultStorage
            ->expects(self::once())
            ->method('set')
            ->with(self::identicalTo($container));
        $lifecycle->startContainer($container);
    }

    public function testUpdateContainer_ContainerNeitherGivenNorStarted_LogsErrorAndReturnsNull(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStub(ResultStorageInterface::class),
            new ThreadContext(),
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
            new ThreadContext(),
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
            new ThreadContext(),
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
            new ThreadContext(),
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
            new ThreadContext(),
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
            new ThreadContext(),
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
            new ThreadContext(),
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
            new ThreadContext(),
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

    public function testStopContainer_ContainerNeitherGivenNorStarted_LogsErrorAndReturnsNull(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStub(ResultStorageInterface::class),
            new ThreadContext(),
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
            new ThreadContext(),
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
            new ThreadContext(),
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

    public function testStopContainer_ContainerNotGivenButStarted_NeverLogsErrorAndReturnsUuid(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
            new ThreadContext(),
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
            new ThreadContext(),
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
            new ThreadContext(),
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
            new ThreadContext(),
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
            new ThreadContext(),
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

    public function testWriteContainer_StorageFailsToProvideContainer_LogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $error = new Exception();
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithoutContainer('a', $error),
            new ThreadContext(),
        );
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Container (UUID: {uuid}) not written'),
                self::identicalTo(['uuid' => 'a', 'exception' => $error]),
            );
        $lifecycle->writeContainer('a');
    }

    public function testWriteContainer_NoExceptionThrownDuringWrite_NotifiesHooksWithoutError(): void
    {
        $hooksNotifier = $this->createMock(HooksNotifierInterface::class);
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $hooksNotifier,
            $this->createStorageWithContainer($container),
            new ThreadContext(),
        );
        $hooksNotifier
            ->expects(self::once())
            ->id('before')
            ->method('beforeContainerWrite')
            ->with(self::identicalTo($container));
        $hooksNotifier
            ->expects(self::once())
            ->after('before')
            ->method('afterContainerWrite')
            ->with(self::identicalTo($container), self::identicalTo(null));
        $lifecycle->writeContainer('a');
    }

    public function testWriteContainer_ExceptionThrownDuringWrite_NotifiesHooksWithError(): void
    {
        $hooksNotifier = $this->createMock(HooksNotifierInterface::class);
        $container = new ContainerResult('a');
        $error = new Exception();
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $hooksNotifier,
            $this->createStorageWithContainer($container, $error),
            new ThreadContext(),
        );
        $hooksNotifier
            ->expects(self::once())
            ->id('before')
            ->method('beforeContainerWrite')
            ->with(self::identicalTo($container));
        $hooksNotifier
            ->expects(self::once())
            ->after('before')
            ->method('afterContainerWrite')
            ->with(self::identicalTo($container), self::identicalTo($error));
        $lifecycle->writeContainer('a');
    }

    public function testWriteContainer_ExceptionThrownDuringWrite_LogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $container = new ContainerResult('a');
        $error = new Exception();
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container, $error),
            new ThreadContext(),
        );
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Container (UUID: {uuid}) not written'),
                self::identicalTo(['uuid' => 'a', 'exception' => $error]),
            );
        $lifecycle->writeContainer('a');
    }

    public function testWriteContainer_ContainerWithGivenUuid_StorageUnsetsResultWithSameUuid(): void
    {
        $container = new ContainerResult('a');
        $resultStorage = $this->createMock(ResultStorageInterface::class);
        $resultStorage
            ->method('getContainer')
            ->willReturn($container);
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $resultStorage,
            new ThreadContext(),
        );

        $resultStorage
            ->expects(self::once())
            ->method('unset')
            ->with(self::identicalTo('a'));
        $lifecycle->writeContainer('a');
    }

    public function testWriteContainer_ExcludedContainerWithNestedResults_RemovesNestedResults(): void
    {
        $resultsWriter = $this->createMock(ResultsWriterInterface::class);

        $container = new ContainerResult('a');

        $setUp = new FixtureResult('b');
        $setUpAttachment = new AttachmentResult('c');
        $setUp->addAttachments($setUpAttachment);
        $setUpStep = new StepResult('d');
        $setUpStepAttachment = new AttachmentResult('e');
        $setUpStep->addAttachments($setUpStepAttachment);
        $setUp->addSteps($setUpStep);
        $container->addSetUps($setUp);

        $test = new TestResult('f');
        $testAttachment = new AttachmentResult('g');
        $test->addAttachments($testAttachment);
        $testStep = new StepResult('h');
        $testStepAttachment = new AttachmentResult('i');
        $testStep->addAttachments($testStepAttachment);
        $test->addSteps($testStep);
        $container->addChildren($test);

        $tearDown = new FixtureResult('j');
        $tearDownAttachment = new AttachmentResult('k');
        $tearDown->addAttachments($tearDownAttachment);
        $tearDownStep = new StepResult('l');
        $tearDownStepAttachment = new AttachmentResult('m');
        $tearDownStep->addAttachments($tearDownStepAttachment);
        $tearDown->addSteps($tearDownStep);
        $container->addTearDowns($tearDown);

        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $resultsWriter,
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
            new ThreadContext(),
        );

        $container->setExcluded(true);
        $resultsWriter
            ->expects(self::exactly(6))
            ->method('removeAttachment')
            ->withConsecutive(
                [self::identicalTo($setUpAttachment)],
                [self::identicalTo($setUpStepAttachment)],
                [self::identicalTo($testAttachment)],
                [self::identicalTo($testStepAttachment)],
                [self::identicalTo($tearDownAttachment)],
                [self::identicalTo($tearDownStepAttachment)],
            );
        $resultsWriter
            ->expects(self::exactly(1))
            ->method('removeTest')
            ->withConsecutive(
                [self::identicalTo($test)],
            );
        $lifecycle->writeContainer('a');
    }

    public function testWriteContainer_ContainerNotExcluded_WriterWritesContainer(): void
    {
        $resultsWriter = $this->createMock(ResultsWriterInterface::class);

        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $resultsWriter,
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
            new ThreadContext(),
        );

        $resultsWriter
            ->expects(self::once())
            ->method('writeContainer')
            ->with(self::identicalTo($container));
        $lifecycle->writeContainer('a');
    }

    public function testWriteContainer_ContainerExcluded_WriterNeverWritesContainer(): void
    {
        $resultsWriter = $this->createMock(ResultsWriterInterface::class);

        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $resultsWriter,
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
            new ThreadContext(),
        );

        $container->setExcluded(true);
        $resultsWriter
            ->expects(self::never())
            ->method('writeContainer');
        $lifecycle->writeContainer('a');
    }

    public function testStartSetUpFixture_ExceptionNotThrownDuringStart_NeverLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
            new ThreadContext(),
        );
        $logger
            ->expects(self::never())
            ->method('error');
        $lifecycle->startSetUpFixture(new FixtureResult('b'), 'a');
    }

    public function testStartSetUpFixture_ContainerNeitherGivenNorStarted_LogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStub(ResultStorageInterface::class),
            new ThreadContext(),
        );
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains(
                    'Set up fixture (UUID: {uuid}, container UUID: {containerUuid}) not started',
                ),
                self::equalTo(
                    ['uuid' => 'b', 'containerUuid' => null, 'exception' => new ActiveContainerNotFoundException()],
                ),
            );
        $lifecycle->startSetUpFixture(new FixtureResult('b'));
    }

    public function testStartSetUpFixture_ExceptionThrownAfterContainerIsProvided_LogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $error = new Exception();
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createFailingClock($error),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer(new ContainerResult('a')),
            new ThreadContext(),
        );
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains(
                    'Set up fixture (UUID: {uuid}, container UUID: {containerUuid}) not started',
                ),
                self::equalTo(
                    ['uuid' => 'b', 'containerUuid' => 'a', 'exception' => $error],
                ),
            );
        $lifecycle->startSetUpFixture(new FixtureResult('b'), 'a');
    }

    public function testStartSetUpFixture_ContainerNotGivenButStarted_NeverLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
            new ThreadContext(),
        );
        $lifecycle->startContainer($container);
        $logger
            ->expects(self::never())
            ->method('error');
        $lifecycle->startSetUpFixture(new FixtureResult('b'));
    }

    public function testStartSetUpFixture_ContainerGiven_NeverLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer(new ContainerResult('a')),
            new ThreadContext(),
        );

        $logger
            ->expects(self::never())
            ->method('error');
        $lifecycle->startSetUpFixture(new FixtureResult('b'), 'a');
    }

    public function testStartSetUpFixture_GivenFixture_ContainerContainsSameFixture(): void
    {
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
            new ThreadContext(),
        );

        $fixture = new FixtureResult('b');
        $lifecycle->startSetUpFixture($fixture, 'a');
        self::assertSame([$fixture], $container->getSetUps());
    }

    public function testStartSetUpFixture_ClockProvidesGivenTime_FixtureStartIsSameTime(): void
    {
        $time = new DateTimeImmutable('@0');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock($time),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer(new ContainerResult('a')),
            new ThreadContext(),
        );

        $fixture = new FixtureResult('b');
        $lifecycle->startSetUpFixture($fixture, 'a');
        self::assertSame($time, $fixture->getStart());
    }

    public function testStartSetUpFixture_GivenFixture_FixtureOnRunningStage(): void
    {
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer(new ContainerResult('a')),
            new ThreadContext(),
        );

        $fixture = new FixtureResult('b');
        $lifecycle->startSetUpFixture($fixture, 'a');
        self::assertSame(Stage::running(), $fixture->getStage());
    }

    public function testStartSetUpFixture_GivenFixture_SameFixtureIsOnlyItemInThreadContextStack(): void
    {
        $threadContext = new ThreadContext();
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer(new ContainerResult('a')),
            $threadContext,
        );

        $fixture = new FixtureResult('b');
        $lifecycle->startSetUpFixture($fixture, 'a');
        self::assertSame(['b'], $this->extractThreadStack($threadContext));
    }

    public function testStartTearDownFixture_ExceptionNotThrownDuringStart_NeverLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
            new ThreadContext(),
        );
        $logger
            ->expects(self::never())
            ->method('error');
        $lifecycle->startTearDownFixture(new FixtureResult('b'), 'a');
    }

    public function testStartTearDownFixture_ContainerNeitherGivenNorStarted_LogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStub(ResultStorageInterface::class),
            new ThreadContext(),
        );
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains(
                    'Tear down fixture (UUID: {uuid}, container UUID: {containerUuid}) not started',
                ),
                self::equalTo(
                    ['uuid' => 'a', 'containerUuid' => null, 'exception' => new ActiveContainerNotFoundException()],
                ),
            );
        $lifecycle->startTearDownFixture(new FixtureResult('a'));
    }

    public function testStartTearDownFixture_ExceptionThrownAfterContainerIsProvided_LogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $error = new Exception();
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createFailingClock($error),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer(new ContainerResult('a')),
            new ThreadContext(),
        );
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains(
                    'Tear down fixture (UUID: {uuid}, container UUID: {containerUuid}) not started',
                ),
                self::equalTo(
                    ['uuid' => 'b', 'containerUuid' => 'a', 'exception' => $error],
                ),
            );
        $lifecycle->startTearDownFixture(new FixtureResult('b'), 'a');
    }

    public function testStartTearDownFixture_ContainerNotGivenButStarted_NeverLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
            new ThreadContext(),
        );
        $lifecycle->startContainer($container);
        $logger
            ->expects(self::never())
            ->method('error');
        $lifecycle->startTearDownFixture(new FixtureResult('b'));
    }

    public function testStartTearDownFixture_ContainerGiven_NeverLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $lifecycle = new AllureLifecycle(
            $logger,
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer(new ContainerResult('a')),
            new ThreadContext(),
        );

        $logger
            ->expects(self::never())
            ->method('error');
        $lifecycle->startTearDownFixture(new FixtureResult('b'), 'a');
    }

    public function testStartTearDownFixture_GivenFixture_ContainerContainsSameFixture(): void
    {
        $container = new ContainerResult('a');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer($container),
            new ThreadContext(),
        );

        $fixture = new FixtureResult('b');
        $lifecycle->startTearDownFixture($fixture, 'a');
        self::assertSame([$fixture], $container->getTearDowns());
    }

    public function testStartTearDownFixture_ClockProvidesGivenTime_FixtureStartIsSameTime(): void
    {
        $time = new DateTimeImmutable('@0');
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock($time),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer(new ContainerResult('a')),
            new ThreadContext(),
        );

        $fixture = new FixtureResult('b');
        $lifecycle->startTearDownFixture($fixture, 'a');
        self::assertSame($time, $fixture->getStart());
    }

    public function testStartTearDownFixture_GivenFixture_FixtureOnRunningStage(): void
    {
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer(new ContainerResult('a')),
            new ThreadContext(),
        );

        $fixture = new FixtureResult('b');
        $lifecycle->startTearDownFixture($fixture, 'a');
        self::assertSame(Stage::running(), $fixture->getStage());
    }

    public function testStartTearDownFixture_GivenFixture_SameFixtureIsOnlyItemInThreadContextStack(): void
    {
        $threadContext = new ThreadContext();
        $lifecycle = new AllureLifecycle(
            $this->createStub(LoggerInterface::class),
            $this->createClock(),
            $this->createStub(ResultsWriterInterface::class),
            $this->createStub(HooksNotifierInterface::class),
            $this->createStorageWithContainer(new ContainerResult('a')),
            $threadContext,
        );

        $fixture = new FixtureResult('b');
        $lifecycle->startTearDownFixture($fixture, 'a');
        self::assertSame(['b'], $this->extractThreadStack($threadContext));
    }

    private function extractThreadStack(ThreadContextInterface $threadContext): array
    {
        $items = [];
        while (null !== $item = $threadContext->getCurrentTestOrStep()) {
            $items[] = $item;
            $threadContext->pop();
        }

        return $items;
    }

    private function createStorageWithContainer(
        ContainerResult $container,
        ?Throwable $unsetError = null,
    ): ResultStorageInterface {
        $resultStorage = $this->createMock(ResultStorageInterface::class);
        $resultStorage
            ->method('getContainer')
            ->with(self::identicalTo($container->getUuid()))
            ->willReturn($container);
        if (isset($unsetError)) {
            $resultStorage
                ->method('unset')
                ->willThrowException($unsetError);
        }

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
