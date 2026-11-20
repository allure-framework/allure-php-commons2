<?php

declare(strict_types=1);

namespace Qameta\Allure\Setup;

use AssertionError;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\IncompleteTest;
use PHPUnit\Framework\RiskyTestError;
use PHPUnit\Framework\SkippedTest;
use Qameta\Allure\Model\Status;
use Qameta\Allure\Model\StatusDetails;
use SebastianBergmann\Exporter\Exporter;
use Throwable;

use function addcslashes;
use function preg_match;

final class DefaultStatusDetector implements StatusDetectorInterface
{
    public function getStatus(Throwable $error): ?Status
    {
        if (
            $error instanceof AssertionError
            || ($error instanceof AssertionFailedError
                && !$error instanceof IncompleteTest
                && !$error instanceof RiskyTestError
                && !$error instanceof SkippedTest)
        ) {
            return Status::failed();
        }

        return Status::broken();
    }

    public function getStatusDetails(Throwable $error): ?StatusDetails
    {
        $errorClass = $error::class;
        $message =
            "{$error->getMessage()}\n" .
            "{$errorClass}({$error->getCode()}) in {$error->getFile()}:{$error->getLine()}";
        /** @psalm-suppress InternalMethod PHPUnit exposes the comparison for an assertion through this method. */
        $comparison = $error instanceof ExpectationFailedException ? $error->getComparisonFailure() : null;

        $details = new StatusDetails(
            message: $message,
            trace: $error->getTraceAsString(),
        );
        if ($comparison !== null) {
            $exporter = new Exporter();
            $actual = $comparison->getActualAsString();
            $expected = $comparison->getExpectedAsString();
            if ($actual === '') {
                $actual = $exporter->export($comparison->getActual());
            }
            if ($expected === '') {
                $expected = $exporter->export($comparison->getExpected());
            }
            if (preg_match('//u', $actual) !== 1 || preg_match('//u', $expected) !== 1) {
                // Escape both sides so a literal backslash cannot be confused with an escaped byte.
                $actual = addcslashes($actual, "\\\x80..\xff");
                $expected = addcslashes($expected, "\\\x80..\xff");
            }
            $details->setActual($actual)->setExpected($expected);
        }

        return $details;
    }
}
