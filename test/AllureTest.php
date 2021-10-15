<?php

declare(strict_types=1);

namespace Qameta\Allure\Test;

use Exception;
use PHPUnit\Framework\TestCase;
use Qameta\Allure\Allure;
use Qameta\Allure\AllureLifecycleInterface;
use Qameta\Allure\Attribute\Parameter;
use Qameta\Allure\Attribute\Title;
use Qameta\Allure\Exception\OutputDirectorySetFailureException;
use Qameta\Allure\Internal\LifecycleBuilder;
use Qameta\Allure\Io\ResultsWriterInterface;
use Qameta\Allure\Io\StreamDataSource;
use Qameta\Allure\Io\StringDataSource;
use Qameta\Allure\Model\AttachmentResult;
use Qameta\Allure\Model\FixtureResult;
use Qameta\Allure\Model\LinkType;
use Qameta\Allure\Model\ParameterMode;
use Qameta\Allure\Model\ResultFactoryInterface;
use Qameta\Allure\Model\Severity;
use Qameta\Allure\Model\Status;
use Qameta\Allure\Model\StatusDetails;
use Qameta\Allure\Model\StepResult;
use Qameta\Allure\Model\TestResult;
use Qameta\Allure\Setup\LifecycleBuilderInterface;
use Qameta\Allure\Setup\StatusDetectorInterface;
use Qameta\Allure\StepContextInterface;
use Throwable;

use const STDOUT;

/**
 * @covers \Qameta\Allure\Allure
 */
class AllureTest extends TestCase
{

    public function setUp(): void
    {
        Allure::reset();
    }

    public function testSetOutputDirectory_GivenPath_PassesSamePathToResultWriterOnLifecycleCreation(): void
    {
        $builder = $this->createMock(LifecycleBuilderInterface::class);
        Allure::setLifecycleBuilder($builder);
        Allure::setOutputDirectory('a');
        $builder
            ->expects(self::once())
            ->method('createResultsWriter')
            ->with(self::identicalTo('a'));
        Allure::getLifecycle();
    }

    public function testSetOutputDirectory_CalledSecondTimeAfterLifecycleCreation_ThrowsException(): void
    {
        Allure::setLifecycleBuilder($this->createLifecycleBuilder());
        Allure::setOutputDirectory('a');
        Allure::getLifecycle();
        $this->expectException(OutputDirectorySetFailureException::class);
        Allure::setOutputDirectory('a');
    }

    public function testGetLifecycleConfigurator_BuilderSet_ReturnsSameBuilder(): void
    {
        $builder = $this->createLifecycleBuilder();
        Allure::setLifecycleBuilder($builder);
        self::assertSame($builder, Allure::getLifecycleConfigurator());
    }

    public function testGetLifecycleConfigurator_BuilderNotSet_ReturnsLifecycleBuilderInstance(): void
    {
        self::assertInstanceOf(LifecycleBuilder::class, Allure::getLifecycleConfigurator());
    }

    public function testGetResultFactory_BuilderProvidesResultFactory_ReturnsSameFactory(): void
    {
        $resultFactory = $this->createStub(ResultFactoryInterface::class);
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder($resultFactory),
        );
        self::assertSame($resultFactory, Allure::getResultFactory());
    }

    public function testGetStatusDetector_BuilderProvidesStatusDetector_ReturnsSameDetector(): void
    {
        $statusDetector = $this->createStub(StatusDetectorInterface::class);
        $builder = $this->createStub(LifecycleBuilderInterface::class);
        $builder
            ->method('getStatusDetector')
            ->willReturn($statusDetector);
        Allure::setLifecycleBuilder($builder);
        self::assertSame($statusDetector, Allure::getStatusDetector());
    }

    public function testGetLifecycle_BuilderProvidesLifecycle_ReturnsSameLifecycle(): void
    {
        $builder = $this->createMock(LifecycleBuilderInterface::class);
        $resultsWriter = $this->createMock(ResultsWriterInterface::class);
        $builder
            ->method('createResultsWriter')
            ->willReturn($resultsWriter);
        $lifecycle = $this->createStub(AllureLifecycleInterface::class);
        Allure::setLifecycleBuilder($builder);
        Allure::setOutputDirectory('a');
        $builder
            ->expects(self::once())
            ->method('createLifecycle')
            ->with(self::identicalTo($resultsWriter))
            ->willReturn($lifecycle);
        self::assertSame($lifecycle, Allure::getLifecycle());
    }

    public function testAddStep_ResultFactoryProvidesStep_LifecycleStartsAndStopsSameStep(): void
    {
        $step = new StepResult('a');
        $lifecycle = $this->createMock(AllureLifecycleInterface::class);
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $lifecycle,
            ),
        );
        Allure::setOutputDirectory('b');
        $lifecycle
            ->expects(self::once())
            ->id('start')
            ->method('startStep')
            ->with(self::identicalTo($step));
        $lifecycle
            ->expects(self::once())
            ->after('start')
            ->method('stopStep')
            ->with(self::identicalTo('a'));
        Allure::addStep('c');
    }

    public function testAddStep_GivenName_StepHasSameName(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder($this->createResultFactoryWithStep($step)),
        );
        Allure::setOutputDirectory('b');
        Allure::addStep('c');
        self::assertSame('c', $step->getName());
    }

    public function testAddStep_NoGivenStatus_StepHasPassedStatus(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder($this->createResultFactoryWithStep($step)),
        );
        Allure::setOutputDirectory('b');
        Allure::addStep('c');
        self::assertSame(Status::passed(), $step->getStatus());
    }

    public function testAddStep_GivenStatus_StepHasSameStatus(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder($this->createResultFactoryWithStep($step)),
        );
        Allure::setOutputDirectory('b');
        $status = Status::failed();
        Allure::addStep('c', $status);
        self::assertSame($status, $step->getStatus());
    }

    /**
     * @throws Throwable
     */
    public function testRunStep_NoExceptionThrownDuringStep_LifecycleStartsAndStopsStep(): void
    {
        $step = new StepResult('a');
        $lifecycle = $this->createMock(AllureLifecycleInterface::class);
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $lifecycle,
            ),
        );
        Allure::setOutputDirectory('b');
        $lifecycle
            ->expects(self::once())
            ->id('start')
            ->method('startStep')
            ->with(self::identicalTo($step));
        $lifecycle
            ->expects(self::once())
            ->after('start')
            ->method('stopStep')
            ->with(self::identicalTo('a'));
        Allure::runStep(fn () => null);
    }

    /**
     * @throws Throwable
     */
    public function testRunStep_ExceptionThrownDuringStep_LifecycleStartsAndStopsStepAndThrowsSameException(): void
    {
        $step = new StepResult('a');
        $lifecycle = $this->createMock(AllureLifecycleInterface::class);
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $lifecycle,
            ),
        );
        Allure::setOutputDirectory('b');
        $lifecycle
            ->expects(self::once())
            ->id('start')
            ->method('startStep')
            ->with(self::identicalTo($step));
        $lifecycle
            ->expects(self::once())
            ->after('start')
            ->method('stopStep')
            ->with(self::identicalTo('a'));
        $error = new Exception('c');
        $this->expectExceptionObject($error);
        Allure::runStep(fn () => throw $error);
    }

    public function testRunStep_NoNameNorTitleAttributeProvided_StepHasDefaultName(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $this->createLifecycleWithUpdatableStep($step),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::runStep(fn () => null);
        self::assertSame('step', $step->getName());
    }

    public function testRunStep_NoNameNorTitleAttributeProvidedButDefaultNameIsSet_StepHasMatchingName(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $this->createLifecycleWithUpdatableStep($step),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::setDefaultStepName('c');
        Allure::runStep(fn () => null);
        self::assertSame('c', $step->getName());
    }

    public function testRunStep_OnlyClosureTitleAttributeProvided_StepHasProvidedName(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $this->createLifecycleWithUpdatableStep($step),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::runStep(#[Title('c')] fn () => null);
        self::assertSame('c', $step->getName());
    }

    public function testRunStep_OnlyMethodTitleAttributeProvided_StepHasMatchingName(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $this->createLifecycleWithUpdatableStep($step),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::runStep([$this, 'titledStep']);
        self::assertSame('c', $step->getName());
    }

    #[Title('c')]
    public function titledStep(): void
    {
    }

    public function testRunStep_BothNameAndTitleAttributeProvided_StepHasMatchingName(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $this->createLifecycleWithUpdatableStep($step),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::runStep(#[Title('c')] fn () => null, 'd');
        self::assertSame('d', $step->getName());
    }

    public function testRunStep_ClosureParameterAttributeProvided_StepHasMatchingParameter(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $this->createLifecycleWithUpdatableStep($step),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::runStep(#[Parameter('c', 'd')] fn () => null);
        $parameter = $step->getParameters()[0] ?? null;
        self::assertSame('c', $parameter?->getName());
        self::assertSame('d', $parameter?->getValue());
    }

    public function testRunStep_MethodParameterAttributeProvided_StepHasMatchingParameter(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $this->createLifecycleWithUpdatableStep($step),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::runStep([$this, 'parametrizedStep']);
        $parameter = $step->getParameters()[0] ?? null;
        self::assertSame('c', $parameter?->getName());
        self::assertSame('d', $parameter?->getValue());
    }

    #[Parameter('c', 'd')]
    public function parametrizedStep(): void
    {
    }

    /**
     * @dataProvider providerRunStepResult
     */
    public function testRunStep_StepReturnsValue_ReturnsSameValue(mixed $value): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder($this->createResultFactoryWithStep($step)),
        );

        Allure::setOutputDirectory('b');
        self::assertSame($value, Allure::runStep(fn (): mixed => $value, 'd'));
    }

    public function testRunStep_NoExceptionThrownDuringStep_StepStatusIsPassed(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $this->createLifecycleWithUpdatableStep($step),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::runStep(fn () => null);
        self::assertSame(Status::passed(), $step->getStatus());
        self::assertNull($step->getStatusDetails());
    }

    public function testRunStep_ExceptionThrownDuringStep_StepStatusIsProvidedByDetector(): void
    {
        $step = new StepResult('a');
        $statusDetector = $this->createMock(StatusDetectorInterface::class);
        $status = Status::failed();
        $error = new Exception();
        $statusDetector
            ->method('getStatus')
            ->with(self::identicalTo($error))
            ->willReturn($status);
        $statusDetails = new StatusDetails();
        $statusDetector
            ->method('getStatusDetails')
            ->with(self::identicalTo($error))
            ->willReturn($statusDetails);

        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $this->createLifecycleWithUpdatableStep($step),
                $statusDetector,
            ),
        );

        Allure::setOutputDirectory('b');
        try {
            Allure::runStep(fn() => throw $error);
        } catch (Throwable) {
        }
        self::assertSame($status, $step->getStatus());
        self::assertSame($statusDetails, $step->getStatusDetails());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public function providerRunStepResult(): iterable
    {
        return [
            'Null' => [null],
            'Integer' => [1],
            'Float' => [1.2],
            'String' => ['a'],
            'Boolean' => [false],
            'Array' => [['a' => 'b']],
            'Object' => [(object) ['a' => 'b']],
            'Resource' => [STDOUT],
            'Callable' => [fn () => null],
        ];
    }

    public function testRunStep_ResultFactoryProvidesStep_CallbackReceivesContextAttachedToSameStep(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $this->createLifecycleWithUpdatableStep($step),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::runStep(fn (StepContextInterface $s) => $s->name('c'));
        self::assertSame('c', $step->getName());
    }

    /**
     * @dataProvider providerAttachmentProperties
     */
    public function testAttachment_ResultFactoryProvidesAttachment_AttachmentHasGivenProperties(
        string $name,
        ?string $type,
        ?string $fileExtension,
    ): void {
        $attachment = new AttachmentResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder($this->createResultFactoryWithAttachment($attachment)),
        );

        Allure::setOutputDirectory('b');
        Allure::attachment($name, 'c', $type, $fileExtension);
        self::assertSame($name, $attachment->getName());
        self::assertSame($type, $attachment->getType());
        self::assertSame($fileExtension, $attachment->getFileExtension());
    }

    /**
     * @return iterable<string, array{string, string|null, string|null}>
     */
    public function providerAttachmentProperties(): iterable
    {
        return [
            'Only name' => ['d', null, null],
            'Name and type' => ['d', 'e', null],
            'Name and file extension' => ['d', null, 'e'],
            'Name, type and file extension' => ['d', 'e', 'f'],
        ];
    }

    public function testAttachment_ResultFactoryProvidesAttachment_AttachmentIsAddedToLifecycle(): void
    {
        $attachment = new AttachmentResult('a');
        $lifecycle = $this->createMock(AllureLifecycleInterface::class);
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithAttachment($attachment),
                $lifecycle,
            ),
        );

        Allure::setOutputDirectory('b');
        $lifecycle
            ->expects(self::once())
            ->method('addAttachment')
            ->with(
                self::identicalTo($attachment),
                self::isInstanceOf(StringDataSource::class),
            );
        Allure::attachment('c', 'd');
    }

    /**
     * @dataProvider providerAttachmentProperties
     */
    public function testAttachmentFile_ResultFactoryProvidesAttachment_AttachmentHasGivenProperties(
        string $name,
        ?string $type,
        ?string $fileExtension,
    ): void {
        $attachment = new AttachmentResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder($this->createResultFactoryWithAttachment($attachment)),
        );

        Allure::setOutputDirectory('b');
        Allure::attachmentFile($name, 'c', $type, $fileExtension);
        self::assertSame($name, $attachment->getName());
        self::assertSame($type, $attachment->getType());
        self::assertSame($fileExtension, $attachment->getFileExtension());
    }

    public function testAttachmentFile_ResultFactoryProvidesAttachment_AttachmentIsAddedToLifecycle(): void
    {
        $attachment = new AttachmentResult('a');
        $lifecycle = $this->createMock(AllureLifecycleInterface::class);
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithAttachment($attachment),
                $lifecycle,
            ),
        );

        Allure::setOutputDirectory('b');
        $lifecycle
            ->expects(self::once())
            ->method('addAttachment')
            ->with(
                self::identicalTo($attachment),
                self::isInstanceOf(StreamDataSource::class),
            );
        Allure::attachmentFile('c', 'd');
    }

    public function testEpic_GivenValue_TestHasMatchingLabel(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::epic('c');
        $label = $test->getLabels()[0] ?? null;
        self::assertSame('epic', $label?->getName());
        self::assertSame('c', $label?->getValue());
    }

    public function testFeature_GivenValue_TestHasMatchingLabel(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::feature('c');
        $label = $test->getLabels()[0] ?? null;
        self::assertSame('feature', $label?->getName());
        self::assertSame('c', $label?->getValue());
    }

    public function testStory_GivenValue_TestHasMatchingLabel(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::story('c');
        $label = $test->getLabels()[0] ?? null;
        self::assertSame('story', $label?->getName());
        self::assertSame('c', $label?->getValue());
    }

    public function testSuite_GivenValue_TestHasMatchingLabel(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::suite('c');
        $label = $test->getLabels()[0] ?? null;
        self::assertSame('suite', $label?->getName());
        self::assertSame('c', $label?->getValue());
    }

    public function testParentSuite_GivenValue_TestHasMatchingLabel(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::parentSuite('c');
        $label = $test->getLabels()[0] ?? null;
        self::assertSame('parentSuite', $label?->getName());
        self::assertSame('c', $label?->getValue());
    }

    public function testSubSuite_GivenValue_TestHasMatchingLabel(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::subSuite('c');
        $label = $test->getLabels()[0] ?? null;
        self::assertSame('subSuite', $label?->getName());
        self::assertSame('c', $label?->getValue());
    }

    public function testSeverity_GivenValue_TestHasMatchingLabel(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::severity(Severity::critical());
        $label = $test->getLabels()[0] ?? null;
        self::assertSame('severity', $label?->getName());
        self::assertSame('critical', $label?->getValue());
    }

    public function testTag_GivenValue_TestHasMatchingLabel(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::tag('c');
        $label = $test->getLabels()[0] ?? null;
        self::assertSame('tag', $label?->getName());
        self::assertSame('c', $label?->getValue());
    }

    public function testOwner_GivenValue_TestHasMatchingLabel(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::owner('c');
        $label = $test->getLabels()[0] ?? null;
        self::assertSame('owner', $label?->getName());
        self::assertSame('c', $label?->getValue());
    }

    public function testLead_GivenValue_TestHasMatchingLabel(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::lead('c');
        $label = $test->getLabels()[0] ?? null;
        self::assertSame('lead', $label?->getName());
        self::assertSame('c', $label?->getValue());
    }

    public function testPackage_GivenValue_TestHasMatchingLabel(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::package('c');
        $label = $test->getLabels()[0] ?? null;
        self::assertSame('package', $label?->getName());
        self::assertSame('c', $label?->getValue());
    }

    public function testLabel_GivenNameAndValue_TestHasMatchingLabel(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::label('c', 'd');
        $label = $test->getLabels()[0] ?? null;
        self::assertSame('c', $label?->getName());
        self::assertSame('d', $label?->getValue());
    }

    public function testParameter_GivenName_TestHasParameterWithSameName(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::parameter('c', null);
        $parameter = $test->getParameters()[0] ?? null;
        self::assertSame('c', $parameter?->getName());
    }

    /**
     * @dataProvider providerParameterValue
     */
    public function testParameter_GivenValue_TestHasParameterWithSameValue(?string $value): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::parameter('c', $value);
        $parameter = $test->getParameters()[0] ?? null;
        self::assertNotNull($parameter);
        self::assertSame($value, $parameter->getValue());
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public function providerParameterValue(): iterable
    {
        return [
            'Null value' => [null],
            'Non-null value' => ['d'],
        ];
    }

    public function testParameter_NotGivenExcludedFlag_TestHasParameterWithFalseExcludedFlag(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::parameter('c', null);
        $parameter = $test->getParameters()[0] ?? null;
        self::assertFalse($parameter?->getExcluded());
    }

    /**
     * @dataProvider providerParameterExcluded
     */
    public function testParameter_GivenExcludedFlag_TestHasParameterWithSameExcludedFlag(bool $excluded): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::parameter('c', null, $excluded);
        $parameter = $test->getParameters()[0] ?? null;
        self::assertNotNull($parameter);
        self::assertSame($excluded, $parameter->getExcluded());
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public function providerParameterExcluded(): iterable
    {
        return [
            'Excluded' => [true],
            'Not excluded' => [false],
        ];
    }

    /**
     * @dataProvider providerParameterMode
     */
    public function testParameter_GivenMode_TestHasParameterWithSameMode(?ParameterMode $mode): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::parameter('c', null, mode: $mode);
        $parameter = $test->getParameters()[0] ?? null;
        self::assertNotNull($parameter);
        self::assertSame($mode, $parameter->getMode());
    }

    /**
     * @return iterable<string, array{ParameterMode|null}>
     */
    public function providerParameterMode(): iterable
    {
        return [
            'Null mode' => [null],
            'Non-null mode' => [ParameterMode::hidden()],
        ];
    }

    public function testIssue_GivenValue_TestHasMatchingLink(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::issue('c', 'd');
        $link = $test->getLinks()[0] ?? null;
        self::assertSame(LinkType::issue(), $link?->getType());
        self::assertSame('c', $link?->getName());
        self::assertSame('d', $link?->getUrl());
    }

    public function testTms_GivenValue_TestHasMatchingLink(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::tms('c', 'd');
        $link = $test->getLinks()[0] ?? null;
        self::assertSame(LinkType::tms(), $link?->getType());
        self::assertSame('c', $link?->getName());
        self::assertSame('d', $link?->getUrl());
    }

    public function testLink_GivenUrl_TestHasLinkWithSameUrl(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::link('c');
        $link = $test->getLinks()[0] ?? null;
        self::assertSame('c', $link?->getUrl());
    }

    /**
     * @dataProvider providerLinkName
     */
    public function testLink_GivenName_TestHasLinkWithSameName(?string $name): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::link('c', $name);
        $link = $test->getLinks()[0] ?? null;
        self::assertNotNull($link);
        self::assertSame($name, $link->getName());
    }

    public function testLink_GivenNoType_TestHasLinkWithCustomType(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::link('c');
        $link = $test->getLinks()[0] ?? null;
        self::assertSame(LinkType::custom(), $link?->getType());
    }

    public function testLink_GivenType_TestHasLinkWithSameType(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        $type = LinkType::tms();
        Allure::link('c', type: $type);
        $link = $test->getLinks()[0] ?? null;
        self::assertSame($type, $link?->getType());
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public function providerLinkName(): iterable
    {
        return [
            'Null name' => [null],
            'Non-null name' => ['d'],
        ];
    }

    public function testDescription_LifecycleUpdatesTest_TestHasSameDescription(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::description('c');
        self::assertSame('c', $test->getDescription());
    }

    public function testDescription_LifecycleUpdatesStep_StepHasSameDescription(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $this->createLifecycleWithUpdatableStep($step),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::description('c');
        self::assertSame('c', $step->getDescription());
    }


    public function testDescriptionHtml_LifecycleUpdatesTest_TestHasSameDescription(): void
    {
        $test = new TestResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithTest($test),
                $this->createLifecycleWithUpdatableTest($test),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::descriptionHtml('c');
        self::assertSame('c', $test->getDescriptionHtml());
    }

    public function testDescriptionHtml_LifecycleUpdatesStep_StepHasSameDescription(): void
    {
        $step = new StepResult('a');
        Allure::setLifecycleBuilder(
            $this->createLifecycleBuilder(
                $this->createResultFactoryWithStep($step),
                $this->createLifecycleWithUpdatableStep($step),
            ),
        );

        Allure::setOutputDirectory('b');
        Allure::descriptionHtml('c');
        self::assertSame('c', $step->getDescriptionHtml());
    }

    private function createLifecycleBuilder(
        ?ResultFactoryInterface $resultFactory = null,
        ?AllureLifecycleInterface $lifecycle = null,
        ?StatusDetectorInterface $statusDetector = null,
    ): LifecycleBuilderInterface {
        $builder = $this->createStub(LifecycleBuilderInterface::class);
        if (isset($resultFactory)) {
            $builder
                ->method('getResultFactory')
                ->willReturn($resultFactory);
        }
        if (isset($lifecycle)) {
            $builder
                ->method('createLifecycle')
                ->willReturn($lifecycle);
        }
        if (isset($statusDetector)) {
            $builder
                ->method('getStatusDetector')
                ->willReturn($statusDetector);
        }

        return $builder;
    }

    private function createResultFactoryWithStep(StepResult $step): ResultFactoryInterface
    {
        $resultFactory = $this->createStub(ResultFactoryInterface::class);
        $resultFactory
            ->method('createStep')
            ->willReturn($step);

        return $resultFactory;
    }

    private function createResultFactoryWithAttachment(AttachmentResult $attachment): ResultFactoryInterface
    {
        $resultFactory = $this->createStub(ResultFactoryInterface::class);
        $resultFactory
            ->method('createAttachment')
            ->willReturn($attachment);

        return $resultFactory;
    }

    private function createResultFactoryWithTest(TestResult $test): ResultFactoryInterface
    {
        $resultFactory = $this->createStub(ResultFactoryInterface::class);
        $resultFactory
            ->method('createTest')
            ->willReturn($test);

        return $resultFactory;
    }

    private function createLifecycleWithUpdatableStep(StepResult $step): AllureLifecycleInterface
    {
        $lifecycle = $this->createMock(AllureLifecycleInterface::class);
        $lifecycle
            ->method('updateStep')
            ->with(
                self::callback(
                    function (callable $callable) use ($step): bool {
                        $callable($step);

                        return true;
                    },
                ),
                self::identicalTo($step->getUuid()),
            );
        $lifecycle
            ->method('updateExecutionContext')
            ->with(
                self::callback(
                    function (callable $callable) use ($step): bool {
                        $callable($step);

                        return true;
                    },
                ),
            );

        return $lifecycle;
    }

    private function createLifecycleWithUpdatableTest(TestResult $test): AllureLifecycleInterface
    {
        $lifecycle = $this->createMock(AllureLifecycleInterface::class);
        $lifecycle
            ->method('updateTest')
            ->with(
                self::callback(
                    function (callable $callable) use ($test): bool {
                        $callable($test);

                        return true;
                    },
                ),
            );
        $lifecycle
            ->method('updateExecutionContext')
            ->with(
                self::callback(
                    function (callable $callable) use ($test): bool {
                        $callable($test);

                        return true;
                    },
                ),
            );

        return $lifecycle;
    }
}
