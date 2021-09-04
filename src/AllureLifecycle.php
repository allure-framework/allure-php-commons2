<?php

namespace Qameta\Allure;

use Psr\Log\LoggerInterface;
use Qameta\Allure\Exception\ActiveContainerNotFoundException;
use Qameta\Allure\Exception\ActiveExecutionContextNotFoundException;
use Qameta\Allure\Exception\ActiveStepNotFoundException;
use Qameta\Allure\Exception\ActiveTestNotFoundException;
use Qameta\Allure\Internal\ThreadContext;
use Qameta\Allure\Internal\ThreadContextInterface;
use Qameta\Allure\Internal\HooksNotifierInterface;
use Qameta\Allure\Internal\LoggerAwareTrait;
use Qameta\Allure\Internal\ResultStorageInterface;
use Qameta\Allure\Io\ClockInterface;
use Qameta\Allure\Io\DataSourceInterface;
use Qameta\Allure\Io\ResultsWriterInterface;
use Qameta\Allure\Model\AttachmentResult;
use Qameta\Allure\Model\ContainerResult;
use Qameta\Allure\Model\FixtureResult;
use Qameta\Allure\Model\ResultInterface;
use Qameta\Allure\Model\Stage;
use Qameta\Allure\Model\StepResult;
use Qameta\Allure\Model\TestResult;
use Throwable;

final class AllureLifecycle implements AllureLifecycleInterface
{
    use LoggerAwareTrait;

    private ThreadContextInterface $threadContext;

    public function __construct(
        LoggerInterface $logger,
        private ClockInterface $clock,
        private ResultsWriterInterface $resultsWriter,
        private HooksNotifierInterface $notifier,
        private ResultStorageInterface $storage,
    ) {
        $this->logger = $logger;
        $this->threadContext = new ThreadContext();
    }

    public function switchThread(?string $thread): void
    {
        $this->threadContext->switchThread($thread);
    }

    public function startContainer(ContainerResult $container): void
    {
        $this->notifier->beforeContainerStart($container);
        try {
            $this->storage->set(
                $container->setStart($this->clock->now()),
            );
            $this->threadContext->setContainer($container->getUuid());
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Container (UUID: {uuid}) not started', $e, ['uuid' => $container->getUuid()]);
            $error = $e;
        }
        $this->notifier->afterContainerStart($container, $error);
    }

    /**
     * @param callable    $update
     * @param string|null $uuid
     * @return string|null
     */
    public function updateContainer(callable $update, ?string $uuid = null): ?string
    {
        try {
            $uuid ??= $this->threadContext->getContainer();
            $container = $this->storage->getContainer($uuid ?? throw new ActiveContainerNotFoundException());
        } catch (Throwable $e) {
            $this->logException('Container (UUID: {uuid}) not updated', $e, ['uuid' => $uuid]);

            return null;
        }
        $this->notifier->beforeContainerUpdate($container);
        try {
            $update($container);
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Container (UUID: {uuid}) not updated', $e, ['uuid' => $container->getUuid()]);
            $error = $e;
        }
        $this->notifier->afterContainerUpdate($container, $error);

        return $container->getUuid();
    }

    public function stopContainer(?string $uuid = null): ?string
    {
        try {
            $uuid ??= $this->threadContext->getContainer();
            $container = $this->storage->getContainer($uuid ?? throw new ActiveContainerNotFoundException());
        } catch (Throwable $e) {
            $this->logException('Container (UUID: {uuid}) not stopped', $e, ['uuid' => $uuid]);

            return null;
        }
        $this->notifier->beforeContainerStop($container);
        try {
            $container->setStop($this->clock->now());
            $this->threadContext->resetContainer();
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Container (UUID: {uuid}) not stopped', $e, ['uuid' => $container->getUuid()]);
            $error = $e;
        }
        $this->notifier->afterContainerStop($container, $error);

        return $container->getUuid();
    }

    public function writeContainer(string $uuid): void
    {
        try {
            $container = $this->storage->getContainer($uuid);
        } catch (Throwable $e) {
            $this->logException('Container (UUID: {uuid}) not written', $e, ['uuid' => $uuid]);

            return;
        }
        $this->notifier->beforeContainerWrite($container);
        try {
            $nestedResults = $container->getNestedResults();
            if ($container->getExcluded()) {
                $this->excludeNestedResults($container);
            }
            $this->removeExcludedNestedResults(...$nestedResults);
            if (!$container->getExcluded()) {
                $this->resultsWriter->writeContainer($container);
            }
            $this->storage->unset($container->getUuid());
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Container (UUID: {uuid}) not written', $e, ['uuid' => $container->getUuid()]);
            $error = $e;
        }
        $this->notifier->afterContainerWrite($container, $error);
    }

    private function excludeNestedResults(ResultInterface $result): void
    {
        foreach ($result->getNestedResults() as $nestedResult) {
            $nestedResult->setExcluded(true);
            $this->excludeNestedResults($nestedResult);
        }
    }

    private function removeExcludedNestedResults(ResultInterface $result): void
    {
        foreach ($result->getNestedResults() as $nestedResult) {
            $this->removeExcludedNestedResults($nestedResult);
            if ($nestedResult->getExcluded()) {
                if ($nestedResult instanceof AttachmentResult) {
                    $this->removeAttachment($nestedResult);
                } elseif ($nestedResult instanceof TestResult) {
                    $this->removeTest($nestedResult);
                } else {
                    $this->logger->error('Result not removed', ['class' => $nestedResult::class]);
                }
            }
        }
    }

    public function startSetUpFixture(FixtureResult $fixture, ?string $containerUuid = null): void
    {
        $this->notifier->beforeFixtureStart($fixture);
        try {
            $containerUuid ??= $this->threadContext->getContainer();
            $this
                ->storage
                ->getContainer($containerUuid ?? throw new ActiveContainerNotFoundException())
                ->addSetUps($fixture);
            $this->startFixture($fixture);
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Fixture (setUp, UUID: {uuid}) not started', $e, ['uuid' => $containerUuid]);
            $error = $e;
        }
        $this->notifier->afterFixtureStart($fixture, $error);
    }

    public function startTearDownFixture(FixtureResult $fixture, ?string $containerUuid = null): void
    {
        $this->notifier->beforeFixtureStart($fixture);
        try {
            $containerUuid ??= $this->threadContext->getContainer();
            $this
                ->storage
                ->getContainer($containerUuid ?? throw new ActiveContainerNotFoundException())
                ->addTearDowns($fixture);
            $this->startFixture($fixture);
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Fixture (tearDown, UUID: {uuid}) not started', $e, ['uuid' => $containerUuid]);
            $error = $e;
        }
        $this->notifier->afterFixtureStart($fixture, $error);
    }

    private function startFixture(FixtureResult $fixture): void
    {
        $this->storage->set(
            $fixture
                ->setStage(Stage::running())
                ->setStart($this->clock->now()),
        );
        $this
            ->threadContext
            ->reset()
            ->push($fixture->getUuid());
    }

    public function updateFixture(callable $update, ?string $uuid = null): ?string
    {
        try {
            $uuid ??= $this->getCurrentTest();
            $fixture = $this->storage->getFixture($uuid ?? throw new ActiveTestNotFoundException());
        } catch (Throwable $e) {
            $this->logException('Fixture (UUID: {uuid}) not updated', $e, ['uuid' => $uuid]);

            return null;
        }
        $this->notifier->beforeFixtureUpdate($fixture);
        try {
            $update($fixture);
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Fixture (UUID: {uuid}) not updated', $e, ['uuid' => $fixture->getUuid()]);
            $error = $e;
        }
        $this->notifier->afterFixtureUpdate($fixture, $error);

        return $fixture->getUuid();
    }

    public function stopFixture(?string $uuid = null): ?string
    {
        try {
            $uuid ??= $this->getCurrentTest();
            $fixture = $this->storage->getFixture($uuid ?? throw new ActiveTestNotFoundException());
        } catch (Throwable $e) {
            $this->logException('Fixture (UUID: {uuid}) not stopped', $e, ['uuid' => $uuid]);

            return null;
        }
        $this->notifier->beforeFixtureStop($fixture);
        try {
            $fixture
                ->setStage(Stage::finished())
                ->setStop($this->clock->now());
            $this->storage->unset($fixture->getUuid());
            $this->threadContext->reset();
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Fixture (UUID: {uuid}) not stopped', $e, ['uuid' => $fixture->getUuid()]);
            $error = $e;
        }
        $this->notifier->afterFixtureStop($fixture, $error);

        return $fixture->getUuid();
    }

    public function getCurrentTest(): ?string
    {
        return $this->threadContext->getCurrentTest();
    }

    public function getCurrentStep(): ?string
    {
        return $this->threadContext->getCurrentStep();
    }

    public function getCurrentTestOrStep(): ?string
    {
        return $this->getCurrentStep() ?? $this->getCurrentTest();
    }

    public function scheduleTest(TestResult $test, ?string $containerUuid = null): void
    {
        $this->notifier->beforeTestSchedule($test);
        $containerUuid ??= $this->threadContext->getContainer();
        try {
            if (isset($containerUuid)) {
                $this
                    ->storage
                    ->getContainer($containerUuid)
                    ->addChildren($test);
            }
            $this->storage->set(
                $test->setStage(Stage::scheduled()),
            );
            $error = null;
        } catch (Throwable $e) {
            $this->logException(
                'Test (UUID: {uuid}) not scheduled (container UUID: {containerUuid})',
                $e,
                ['uuid' => $test->getUuid(), 'containerUuid' => $containerUuid],
            );
            $error = $e;
        }
        $this->notifier->afterTestSchedule($test, $error);
    }

    public function startTest(string $uuid): void
    {
        try {
            $test = $this->storage->getTest($uuid);
        } catch (Throwable $e) {
            $this->logException('Test (UUID: {uuid}) not started', $e, ['uuid' => $uuid]);

            return;
        }
        $this->notifier->beforeTestStart($test);
        try {
            $test
                ->setStage(Stage::running())
                ->setStart($this->clock->now());
            $this
                ->threadContext
                ->reset()
                ->push($test->getUuid());
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Test (UUID: {uuid}) not started', $e, ['uuid' => $test->getUuid()]);
            $error = $e;
        }
        $this->notifier->afterTestStart($test, $error);
    }

    public function updateTest(callable $update, ?string $uuid = null): ?string
    {
        try {
            $uuid ??= $this->getCurrentTest();
            $test = $this->storage->getTest($uuid ?? throw new ActiveTestNotFoundException());
        } catch (Throwable $e) {
            $this->logException('Test (UUID: {uuid}) not updated', $e, ['uuid' => $uuid]);

            return null;
        }
        $this->notifier->beforeTestUpdate($test);
        try {
            $update($test);
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Test (UUID: {uuid}) not updated', $e, ['uuid' => $test->getUuid()]);
            $error = $e;
        }
        $this->notifier->afterTestUpdate($test, $error);

        return $test->getUuid();
    }

    public function stopTest(?string $uuid = null): ?string
    {
        try {
            $uuid ??= $this->getCurrentTest();
            $test = $this->storage->getTest($uuid ?? throw new ActiveTestNotFoundException());
        } catch (Throwable $e) {
            $this->logException('Test (UUID: {uuid}) not stopped', $e, ['uuid' => $uuid]);

            return null;
        }
        $this->notifier->beforeTestStop($test);
        try {
            $test
                ->setStage(Stage::finished())
                ->setStop($this->clock->now());
            $this->threadContext->reset();
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Test (UUID: {uuid}) not stopped', $e, ['uuid' => $test->getUuid()]);
            $error = $e;
        }
        $this->notifier->afterTestStop($test, $error);

        return $test->getUuid();
    }

    public function writeTest(string $uuid): void
    {
        try {
            $test = $this->storage->getTest($uuid);
        } catch (Throwable $e) {
            $this->logException('Test (UUID: {uuid}) not written', $e, ['uuid' => $uuid]);

            return;
        }
        $this->notifier->beforeTestWrite($test);
        try {
            if ($test->getExcluded()) {
                $this->excludeNestedResults($test);
            }
            $this->removeExcludedNestedResults($test);
            if (!$test->getExcluded()) {
                $this->resultsWriter->writeTest($test);
            }
            $this->storage->unset($test->getUuid());
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Test (UUID: {uuid}) not written', $e, ['uuid' => $test->getUuid()]);
            $error = $e;
        }
        $this->notifier->afterTestWrite($test, $error);
    }

    private function removeTest(TestResult $test): void
    {
        try {
            $this->resultsWriter->removeTest($test);
        } catch (Throwable $e) {
            $this->logException('Test (UUID: {uuid}) not removed', $e, ['uuid' => $test->getUuid()]);
        }
    }

    public function startStep(StepResult $step, ?string $parentUuid = null): void
    {
        $this->notifier->beforeStepStart($step);
        try {
            $parentUuid ??= $this->getCurrentTestOrStep();
            $this
                ->storage
                ->getExecutionContext($parentUuid ?? throw new ActiveExecutionContextNotFoundException())
                ->addSteps($step);
            $this
                ->storage
                ->set(
                    $step
                        ->setStage(Stage::running())
                        ->setStart($this->clock->now()),
                );
            $this->threadContext->push($step->getUuid());
            $error = null;
        } catch (Throwable $e) {
            $this->logException(
                'Step (UUID: {uuid}) not started (parent UUID: {parentUuid})',
                $e,
                ['uuid' => $step->getUuid(), 'parentUuid' => $parentUuid],
            );
            $error = $e;
        }
        $this->notifier->afterStepStart($step, $error);
    }

    public function updateStep(callable $update, ?string $uuid = null): ?string
    {
        try {
            $uuid ??= $this->getCurrentStep();
            $step = $this->storage->getStep($uuid ?? throw new ActiveStepNotFoundException());
        } catch (Throwable $e) {
            $this->logException('Step (UUID: {uuid}) not updated', $e, ['uuid' => $uuid]);

            return null;
        }
        $this->notifier->beforeStepUpdate($step);
        try {
            $update($step);
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Step (UUID: {uuid}) not updated', $e, ['uuid' => $step->getUuid()]);
            $error = $e;
        }
        $this->notifier->afterStepUpdate($step, $error);

        return $step->getUuid();
    }

    public function updateExecutionContext(callable $update, ?string $uuid = null): ?string
    {
        try {
            $uuid ??= $this->getCurrentTestOrStep();
            $context = $this->storage->getExecutionContext(
                $uuid ?? throw new ActiveExecutionContextNotFoundException()
            );
        } catch (Throwable $e) {
            $this->logException('Execution context (UUID: {uuid}) not updated', $e, ['uuid' => $uuid]);

            return null;
        }

        if ($context instanceof FixtureResult) {
            return $this->updateFixture($update, $context->getUuid());
        }
        if ($context instanceof TestResult) {
            return $this->updateTest($update, $context->getUuid());
        }
        if ($context instanceof StepResult) {
            return $this->updateStep($update, $context->getUuid());
        }

        $this->logger->error(
            'Execution context (UUID: {uuid}) not updated',
            ['uuid' => $context->getUuid()],
        );

        return null;
    }

    public function stopStep(?string $uuid = null): ?string
    {
        $uuid ??= $this->getCurrentStep();
        try {
            $step = $this->storage->getStep($uuid ?? throw new ActiveStepNotFoundException());
        } catch (Throwable $e) {
            $this->logException('Step (UUID: {uuid}) not stopped', $e, ['uuid' => $uuid]);

            return null;
        }
        $this->notifier->beforeStepStop($step);
        try {
            $step
                ->setStage(Stage::finished())
                ->setStop($this->clock->now());
            $this->storage->unset($step->getUuid());
            $this->threadContext->pop();
            $error = null;
        } catch (Throwable $e) {
            $this->logException('Step (UUID: {uuid}) not stopped', $e, ['uuid' => $step->getUuid()]);
            $error = $e;
        }
        $this->notifier->afterStepStop($step, $error);

        return $step->getUuid();
    }

    public function addAttachment(AttachmentResult $attachment, DataSourceInterface $data): void
    {
        $parentUuid = $this->getCurrentTestOrStep();
        try {
            $context = $this
                ->storage
                ->getExecutionContext($parentUuid ?? throw new ActiveExecutionContextNotFoundException())
                ->addAttachments($attachment);
        } catch (Throwable $e) {
            $this->logException(
                'Attachment (UUID: {uuid}) not added (parent UUID: {parentUuid})',
                $e,
                ['uuid' => $attachment->getUuid(), 'parentUuid' => $parentUuid],
            );

            return;
        }
        $this->notifier->beforeAttachmentWrite($attachment);
        try {
            if (!$attachment->getExcluded()) {
                $this->resultsWriter->writeAttachment($attachment, $data);
            }
            $error = null;
        } catch (Throwable $e) {
            $this->logException(
                'Attachment (UUID: {uuid}) not added (parent UUID: {parentUuid})',
                $e,
                ['uuid' => $attachment->getUuid(), 'parentUuid' => $context->getUuid()],
            );
            $error = $e;
        }
        $this->notifier->afterAttachmentWrite($attachment, $error);
    }

    private function removeAttachment(AttachmentResult $attachment): void
    {
        try {
            $this->resultsWriter->removeAttachment($attachment);
        } catch (Throwable $e) {
            $this->logException('Attachment (UUID: {uuid}) not removed', $e, ['uuid' => $attachment->getUuid()]);
        }
    }
}
