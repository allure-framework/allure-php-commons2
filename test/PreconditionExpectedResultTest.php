<?php

declare(strict_types=1);

namespace Qameta\Allure\Test;

use PHPUnit\Framework\TestCase;
use Qameta\Allure\Allure;
use Qameta\Allure\Model\TestResult;

use function file_get_contents;
use function glob;
use function json_decode;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * @covers \Qameta\Allure\Allure
 * @covers \Qameta\Allure\Model\TestResult
 */
class PreconditionExpectedResultTest extends TestCase
{
    private string $outputDirectory;

    public function setUp(): void
    {
        $this->outputDirectory = sys_get_temp_dir() . '/allure-sections-' . uniqid('', true);
        mkdir($this->outputDirectory);
        Allure::reset();
        Allure::getLifecycleConfigurator()->setOutputDirectory($this->outputDirectory);
    }

    public function tearDown(): void
    {
        Allure::reset();
        $files = glob($this->outputDirectory . '/*');
        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }
        rmdir($this->outputDirectory);
    }

    /**
     * @dataProvider sectionsProvider
     * @param array<string, string> $sections
     */
    public function testRuntimeSectionsAreWrittenToTestJson(array $sections, bool $insideStep): void
    {
        $test = new TestResult('test');
        $lifecycle = Allure::getLifecycle();
        $lifecycle->scheduleTest($test);
        $lifecycle->startTest($test->getUuid());
        Allure::description('Existing **description**');

        $setSections = static function () use ($sections): void {
            foreach ($sections as $method => $value) {
                Allure::$method($value);
            }
        };
        if ($insideStep) {
            Allure::runStep($setSections, 'Nested step');
        } else {
            $setSections();
        }

        $lifecycle->stopTest();
        $lifecycle->writeTest($test->getUuid());

        /** @var array<string, mixed> $result */
        $result = json_decode(
            (string) file_get_contents($this->outputDirectory . '/test-result.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame('Existing **description**', $result['description']);
        self::assertArrayNotHasKey('excluded', $result);
        foreach (['precondition', 'preconditionHtml', 'expectedResult', 'expectedResultHtml'] as $field) {
            if (isset($sections[$field])) {
                self::assertSame($sections[$field], $result[$field] ?? null);
            } else {
                self::assertArrayNotHasKey($field, $result);
            }
        }
        /** @var list<array<string, mixed>> $steps */
        $steps = $result['steps'];
        self::assertCount($insideStep ? 1 : 0, $steps);
        foreach ($steps as $step) {
            foreach (['precondition', 'preconditionHtml', 'expectedResult', 'expectedResultHtml'] as $field) {
                self::assertArrayNotHasKey($field, $step);
            }
        }
    }

    /**
     * @return iterable<string, array{array<string, string>, bool}>
     */
    public static function sectionsProvider(): iterable
    {
        yield 'unset' => [[], false];
        $markdown = [
            'precondition' => 'A registered user exists and is **signed out**.',
            'expectedResult' => 'The user is redirected to the **dashboard**.',
        ];
        $html = [
            'preconditionHtml' => '<p>A registered user exists and is <strong>signed out</strong>.</p>',
            'expectedResultHtml' => '<p>The user is redirected to the <strong>dashboard</strong>.</p>',
        ];
        yield 'markdown' => [$markdown, false];
        yield 'html' => [$html, false];
        yield 'markdown inside step' => [$markdown, true];
        yield 'html inside step' => [$html, true];
        yield 'mixed formats' => [[
            'precondition' => $markdown['precondition'],
            'expectedResultHtml' => $html['expectedResultHtml'],
        ], false];
        yield 'empty strings' => [['precondition' => '', 'expectedResultHtml' => ''], false];
        yield 'zero strings' => [['preconditionHtml' => '0', 'expectedResult' => '0'], false];
        yield 'explicitly supplied formats' => [$markdown + $html, false];
    }

    public function testModelSectionsCanBeReplacedAndUnsetIndependently(): void
    {
        $test = new TestResult('test');
        self::assertNull($test->getPrecondition());
        self::assertNull($test->getPreconditionHtml());
        self::assertNull($test->getExpectedResult());
        self::assertNull($test->getExpectedResultHtml());
        self::assertSame($test, $test->setPrecondition('old'));
        self::assertSame($test, $test->setPreconditionHtml('<p>old</p>'));
        self::assertSame($test, $test->setExpectedResult('old'));
        self::assertSame($test, $test->setExpectedResultHtml('<p>old</p>'));

        $test->setPrecondition('new')->setExpectedResultHtml('<p>new</p>');
        self::assertSame('new', $test->getPrecondition());
        self::assertSame('<p>old</p>', $test->getPreconditionHtml());
        self::assertSame('old', $test->getExpectedResult());
        self::assertSame('<p>new</p>', $test->getExpectedResultHtml());

        $test->setPrecondition(null)->setPreconditionHtml(null)->setExpectedResult(null)->setExpectedResultHtml(null);
        foreach (['precondition', 'preconditionHtml', 'expectedResult', 'expectedResultHtml'] as $field) {
            self::assertArrayNotHasKey($field, $test->jsonSerialize());
        }
    }
}
