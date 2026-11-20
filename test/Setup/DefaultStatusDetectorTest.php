<?php

declare(strict_types=1);

namespace Qameta\Allure\Test\Setup;

use AssertionError;
use Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\IncompleteTestError;
use PHPUnit\Framework\RiskyTestError;
use PHPUnit\Framework\SkippedTestError;
use PHPUnit\Framework\TestCase;
use Qameta\Allure\Model\Status;
use Qameta\Allure\Setup\DefaultStatusDetector;

/**
 * @covers \Qameta\Allure\Setup\DefaultStatusDetector
 */
class DefaultStatusDetectorTest extends TestCase
{
    /**
     * @psalm-suppress InternalClass PHPUnit runner errors are constructed to exercise their classification.
     * @psalm-suppress InternalMethod
     */
    public function testGetStatus_NonAssertionErrors_ReturnBrokenStatus(): void
    {
        $detector = new DefaultStatusDetector();
        self::assertSame(Status::broken(), $detector->getStatus(new Exception()));
        self::assertSame(Status::broken(), $detector->getStatus(new SkippedTestError()));
        self::assertSame(Status::broken(), $detector->getStatus(new IncompleteTestError()));
        self::assertSame(Status::broken(), $detector->getStatus(new RiskyTestError()));
    }

    public function testGetStatus_NativeAssertionError_ReturnsFailedStatus(): void
    {
        $detector = new DefaultStatusDetector();
        self::assertSame(Status::failed(), $detector->getStatus(new AssertionError()));
    }

    public function testGetStatusDetails_GivenError_ReturnsMatchingDetails(): void
    {
        $detector = new DefaultStatusDetector();
        $error = new Exception('abracadabra', 1);
        $details = $detector->getStatusDetails($error);
        self::assertStringContainsString('abracadabra', $details?->getMessage() ?? '');
        self::assertStringContainsString('Exception(1)', $details?->getMessage() ?? '');
        self::assertSame($error->getTraceAsString(), $details?->getTrace());
    }

    public function testGetStatusDetails_BinaryComparison_ReturnsValidUtf8(): void
    {
        try {
            self::assertSame("\\377", "\xff");
        } catch (ExpectationFailedException $exception) {
            $details = (new DefaultStatusDetector())->getStatusDetails($exception);
            self::assertSame("'\\377'", $details?->getActual());
            self::assertSame("'\\\\377'", $details?->getExpected());
        }
    }
}
