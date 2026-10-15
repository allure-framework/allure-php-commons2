<?php

declare(strict_types=1);

namespace Qameta\Allure\Test\Model;

use PHPUnit\Framework\TestCase;
use Qameta\Allure\Model\GlobalError;
use Qameta\Allure\Model\Status;
use DateTimeImmutable;
use DateTimeInterface;

use function json_decode;
use function json_encode;

/**
 * @covers \Qameta\Allure\Model\GlobalError
 */
class GlobalErrorTest extends TestCase
{
    public function testGetTimestamp_CreatedWithTimestampOnly_ReturnsProvidedValue(): void
    {
        $date = new DateTimeImmutable();
        $globalError = new GlobalError($date);

        self::assertEquals($date, $globalError->getTimestamp());
    }

    public function testGetStatus_ConstructedWithStatus_ReturnsProvidedValue(): void
    {
        $globalError = new GlobalError(
            timestamp: new DateTimeImmutable(),
            status: Status::failed(),
        );

        self::assertSame(Status::failed(), $globalError->getStatus());
    }

    public function testConstruct_PositionalKnownAfterTimestamp_PreservesLegacyArgumentOrder(): void
    {
        $date = new DateTimeImmutable();
        $globalError = new GlobalError($date, true, false, true, "foo", "bar");

        self::assertEquals($date, $globalError->getTimestamp());
        self::assertTrue($globalError->isKnown());
        self::assertFalse($globalError->isMuted());
        self::assertTrue($globalError->isFlaky());
        self::assertEquals("foo", $globalError->getMessage());
        self::assertEquals("bar", $globalError->getTrace());
        self::assertNull($globalError->getStatus());
    }

    public function testStatusDetailsProperties_ConstructedWithAllArguments_ReturnProvidedValues(): void
    {
        $globalError = new GlobalError(
            timestamp: new DateTimeImmutable(),
            known: true,
            muted: true,
            flaky: true,
            message: "foo",
            trace: "bar",
            status: Status::broken(),
        );

        self::assertSame(Status::broken(), $globalError->getStatus());
        self::assertTrue($globalError->isKnown());
        self::assertTrue($globalError->isMuted());
        self::assertTrue($globalError->isFlaky());
        self::assertEquals("foo", $globalError->getMessage());
        self::assertEquals("bar", $globalError->getTrace());
    }

    public function testGetTimestamp_AfterSetTimestamp_ReturnsUpdatedValue(): void
    {
        $globalError = new GlobalError(new DateTimeImmutable());

        /**
         * @var DateTimeImmutable
         */
        $expectedDate = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ISO8601,
            "2020-01-01T10:20:31+00:00",
        );

        $globalError->setTimestamp($expectedDate);

        self::assertEquals($expectedDate, $globalError->getTimestamp());
    }

    public function testSetTimestamp_ReturnsObjectItself(): void
    {
        $globalError = new GlobalError(new DateTimeImmutable());

        self::assertSame(
            $globalError,
            $globalError->setTimestamp(new DateTimeImmutable()),
        );
    }

    public function testSetStatus_ReturnsObjectItselfAndUpdatesValue(): void
    {
        $globalError = new GlobalError(new DateTimeImmutable());

        self::assertSame($globalError, $globalError->setStatus(Status::failed()));
        self::assertSame(Status::failed(), $globalError->getStatus());
    }

    public function testJsonSerialization_WithAllProperties_JsonContainsAllProperties(): void
    {
        /**
         * @var DateTimeImmutable
         */
        $date = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ISO8601,
            "2020-01-01T10:20:31+00:00", // 1577874031000 milliseconds since epoch
        );
        $globalError = new GlobalError(
            timestamp: $date,
            known: true,
            muted: true,
            flaky: true,
            message: "foo",
            trace: "bar",
            status: Status::failed(),
        );

        $json = json_encode($globalError);

        /** @var object */
        $parsed = json_decode($json);

        self::assertEquals(1577874031000, $parsed->timestamp);
        self::assertEquals("failed", $parsed->status);
        self::assertEquals("foo", $parsed->message);
        self::assertEquals("bar", $parsed->trace);
        self::assertTrue($parsed->known);
        self::assertTrue($parsed->muted);
        self::assertTrue($parsed->flaky);
    }
}
