<?php

declare(strict_types=1);

namespace Qameta\Allure\Test\Model;

use PHPUnit\Framework\TestCase;
use Qameta\Allure\Model\GlobalAttachment;
use Qameta\Allure\Model\ResultFactory;
use DateTimeImmutable;
use DateTimeInterface;

use function json_decode;
use function json_encode;

/**
 * @covers \Qameta\Allure\Model\GlobalAttachment
 */
class GlobalAttachmentTest extends TestCase
{
    public function testRequiredProperties_ReturnProvidedValue(): void
    {
        $date = new DateTimeImmutable();

        $globalAttachment = new GlobalAttachment(
            uuid: "-",
            timestamp: $date,
        );

        self::assertEquals("-", $globalAttachment->getUuid());
        self::assertEquals($date, $globalAttachment->getTimestamp());
    }

    public function testNonMandatoryProperties_WhenNotProvided_DefaultToNull(): void
    {
        $globalAttachment = new GlobalAttachment(
            uuid: "-",
            timestamp: new DateTimeImmutable(),
        );

        self::assertNull($globalAttachment->getName());
        self::assertNull($globalAttachment->getSource());
        self::assertNull($globalAttachment->getType());
        self::assertNull($globalAttachment->getFileExtension());
    }

    public function testNonMandatoryProperties_WhenProvided_EqualProvidedValues(): void
    {
        $globalAttachment = new GlobalAttachment(
            uuid: "-",
            timestamp: new DateTimeImmutable(),
            name: "foo",
            source: "bar",
            type: "baz",
            fileExtension: "qux",
        );

        self::assertEquals("foo", $globalAttachment->getName());
        self::assertEquals("bar", $globalAttachment->getSource());
        self::assertEquals("baz", $globalAttachment->getType());
        self::assertEquals("qux", $globalAttachment->getFileExtension());
    }

    public function testGetTimestamp_AfterSetTimestamp_ReturnsUpdatedValue(): void
    {
        $globalAttachment = new GlobalAttachment(
            uuid: "-",
            timestamp: new DateTimeImmutable(),
        );

        /**
         * @var DateTimeImmutable
         */
        $expectedDate = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ISO8601,
            "2020-01-01T10:20:31+00:00",
        );

        $globalAttachment->setTimestamp($expectedDate);

        self::assertEquals($expectedDate, $globalAttachment->getTimestamp());
    }

    public function testSetTimestamp_ReturnsObjectItself(): void
    {
        $globalAttachment = new GlobalAttachment(
            uuid: "-",
            timestamp: new DateTimeImmutable(),
        );

        self::assertSame(
            $globalAttachment,
            $globalAttachment->setTimestamp(new DateTimeImmutable()),
        );
    }

    public function testJsonSerialization_WithAllProperties_JsonContainsAllProperties(): void
    {
        /**
         * @var DateTimeImmutable
         */
        $date = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ISO8601,

            // 1577874031000 milliseconds since epoch
            "2020-01-01T10:20:31+00:00",
        );
        $globalAttachment = new GlobalAttachment(
            uuid: "-",
            timestamp: $date,
            name: "foo",
            source: "bar",
            type: "baz",
            fileExtension: "qux",
        );

        $json = json_encode($globalAttachment);

        /** @var object */
        $parsed = json_decode($json);

        self::assertEquals(1577874031000, $parsed->timestamp);
        self::assertEquals("foo", $parsed->name);
        self::assertEquals("bar", $parsed->source);
        self::assertEquals("baz", $parsed->type);
    }
}
