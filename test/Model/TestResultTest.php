<?php

declare(strict_types=1);

namespace Qameta\Allure\Test\Model;

use JsonException;
use PHPUnit\Framework\TestCase;
use Qameta\Allure\Model\TestResult;

use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

/**
 * @covers \Qameta\Allure\Model\TestResult

 */
class TestResultTest extends TestCase
{
    public function testGetTitlePathReturnsEmptyArrayByDefault(): void
    {
        $testResult = new TestResult("e42e48d0-7a3f-4fba-8518-8e6ada04af1d");

        $titlePath = $testResult->getTitlePath();

        self::assertEquals($titlePath, []);
    }

    public function testSetTitlePathReturnsObjectItself(): void
    {
        $testResult = new TestResult("e42e48d0-7a3f-4fba-8518-8e6ada04af1d");

        $actual = $testResult->setTitlePath();

        self::assertSame($actual, $testResult);
    }

    public function testTitlePathCanBeSet(): void
    {
        $testResult = new TestResult("e42e48d0-7a3f-4fba-8518-8e6ada04af1d");
        $testResult->setTitlePath("foo", "bar", "baz");

        $titlePath = $testResult->getTitlePath();

        self::assertEquals($titlePath, ["foo", "bar", "baz"]);
    }

    public function testTitlePathJsonSerialization()
    {
        $testResult = new TestResult("e42e48d0-7a3f-4fba-8518-8e6ada04af1d");
        $testResult->setTitlePath("foo", "bar", "baz");

        $decodedJson = json_decode(json_encode($testResult));

        self::assertEquals($decodedJson->titlePath, ["foo", "bar", "baz"]);
    }
}
