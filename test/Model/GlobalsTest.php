<?php

declare(strict_types=1);

namespace Qameta\Allure\Test\Model;

use PHPUnit\Framework\TestCase;
use Qameta\Allure\Model\GlobalAttachment;
use Qameta\Allure\Model\GlobalError;
use Qameta\Allure\Model\Globals;
use DateTimeImmutable;
use DateTimeInterface;

use function json_decode;
use function json_encode;

/**
 * @covers \Qameta\Allure\Model\Globals
 */
class GlobalsTest extends TestCase
{
    public function testGetUuid_Default_ReturnsProvidedValue(): void
    {
        $globals = new Globals("-");

        self::assertEquals("-", $globals->getUuid());
    }

    public function testGetResultType_Always_ReturnsGlobals(): void
    {
        $globals = new Globals("-");

        self::assertEquals("globals", (string)$globals->getResultType());
    }

    public function testGlobalProperties_Default_AreEmpty(): void
    {
        $globals = new Globals("-");

        self::assertEquals([], $globals->getAttachments());
        self::assertEquals([], $globals->getErrors());
    }

    public function testGetAttachments_AfterAttachmentAdded_ContainsSingleAttachment(): void
    {
        $globals = new Globals("-");
        $attachment = new GlobalAttachment(
            uuid: "-",
            source: "foo",
            timestamp: new DateTimeImmutable(),
        );

        $globals->addAttachment($attachment);


        self::assertEquals([$attachment], $globals->getAttachments());
    }

    public function testAddAttachment_Always_ReturnsObjectItself(): void
    {
        $globals = new Globals("-");
        $attachment = new GlobalAttachment(
            uuid: "-",
            source: "foo",
            timestamp: new DateTimeImmutable(),
        );

        self::assertSame($globals, $globals->addAttachment($attachment));
    }

    public function testGetAttachments_AfterTwoAttachmentsAdded_ContainsBothAttachments(): void
    {
        $globals = new Globals("-");
        $attachment1 = new GlobalAttachment(
            uuid: "-",
            source: "foo",
            timestamp: new DateTimeImmutable(),
        );
        $attachment2 = new GlobalAttachment(
            uuid: "-",
            source: "foo",
            timestamp: new DateTimeImmutable(),
        );

        $globals
            ->addAttachment($attachment1)
            ->addAttachment($attachment2);


        self::assertEquals([$attachment1, $attachment2], $globals->getAttachments());
    }

    public function testGetNestedResults_AfterMultipleItemsAdded_ContainsAllAttachmentsOnly(): void
    {
        $globals = new Globals("-");
        $attachment1 = new GlobalAttachment(
            uuid: "-",
            source: "foo",
            timestamp: new DateTimeImmutable(),
        );
        $attachment2 = new GlobalAttachment(
            uuid: "-",
            source: "foo",
            timestamp: new DateTimeImmutable(),
        );

        $globals
            ->addAttachment($attachment1)
            ->addAttachment($attachment2)
            ->addError(new GlobalError(
                timestamp: new DateTimeImmutable(),
            ));


        self::assertEquals([$attachment1, $attachment2], $globals->getNestedResults());
    }

    public function testGetErrors_AfterErrorAdded_ContainsSingleError(): void
    {
        $globals = new Globals("-");
        $error = new GlobalError(
            timestamp: new DateTimeImmutable(),
        );

        $globals->addError($error);


        self::assertEquals([$error], $globals->getErrors());
    }

    public function testAddError_Always_ReturnsObjectItself(): void
    {
        $globals = new Globals("-");
        $error = new GlobalError(
            timestamp: new DateTimeImmutable(),
        );

        self::assertSame($globals, $globals->addError($error));
    }

    public function testGetErrors_AfterTwoErrorsAdded_ContainsBothError(): void
    {
        $globals = new Globals("-");
        $error1 = new GlobalError(
            timestamp: new DateTimeImmutable(),
        );
        $error2 = new GlobalError(
            timestamp: new DateTimeImmutable(),
        );

        $globals
            ->addError($error1)
            ->addError($error2);


        self::assertEquals([$error1, $error2], $globals->getErrors());
    }

    public function testJsonSerialization_WithAllProperties_JsonContainsAllProperties(): void
    {
        /**
         * @var DateTimeImmutable
         */
        $date1 = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ISO8601,
            "2020-01-01T10:20:31+00:00", // 1577874031000 milliseconds since epoch
        );

        $date2 = $date1->modify("+30 seconds"); // 1577874061000 milliseconds since epoch

        $globals = new Globals("-");

        $globals
            ->addAttachment(
                new GlobalAttachment(
                    uuid: "-",
                    source: "foo",
                    timestamp: $date1,
                ),
            )
            ->addError(new GlobalError(timestamp: $date2));

        $json = json_encode($globals);

        /** @var object */
        $parsed = json_decode($json);
        /** @var array */
        $parsedAttachments = $parsed->attachments;
        /** @var array */
        $parsedErrors = $parsed->errors;

        self::assertCount(1, $parsedAttachments);
        self::assertCount(1, $parsedErrors);

        /** @var object */
        $parsedAttachment = $parsedAttachments[0];
        /** @var object */
        $parsedError = $parsedErrors[0];

        self::assertEquals($parsedAttachment->source, "foo");
        self::assertEquals($parsedAttachment->timestamp, 1577874031000);
        self::assertEquals($parsedError->timestamp, 1577874061000);
    }
}
