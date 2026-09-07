<?php

declare(strict_types=1);

namespace Qameta\Allure\Test\Io;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Qameta\Allure\Io\Exception\IoFailedException;
use Qameta\Allure\Io\FileSystemResultsWriter;
use Qameta\Allure\Io\StringDataSource;
use Qameta\Allure\Model\AttachmentResult;
use Qameta\Allure\Model\ContainerResult;
use Qameta\Allure\Model\Globals;
use Qameta\Allure\Model\TestResult;
use Throwable;

use function array_filter;
use function array_map;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function in_array;
use function is_dir;
use function json_decode;
use function mkdir;
use function rmdir;
use function scandir;
use function strpos;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

/**
 * @covers \Qameta\Allure\Io\FileSystemResultsWriter
 */
class FileSystemResultsWriterTest extends TestCase
{
    private string $outputDirectory;

    public function setUp(): void
    {
        $this->outputDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'allure-fs-writer-'
            . uniqid('', true);
        mkdir($this->outputDirectory, 0777, true);
    }

    public function tearDown(): void
    {
        if (!is_dir($this->outputDirectory)) {
            return;
        }
        $entries = scandir($this->outputDirectory);
        if (false === $entries) {
            return;
        }
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            @unlink($this->outputDirectory . DIRECTORY_SEPARATOR . $entry);
        }
        @rmdir($this->outputDirectory);
    }

    /**
     * @throws Throwable
     */
    public function testWriteTest_SuccessfulPublish_CreatesFinalJsonWithoutLeftoverTemps(): void
    {
        $uuid = 'test-uuid-1';
        $writer = new FileSystemResultsWriter($this->outputDirectory, new NullLogger());
        $writer->writeTest(new TestResult($uuid));

        $final = $this->outputDirectory . DIRECTORY_SEPARATOR . $uuid . '-result.json';
        self::assertFileExists($final);
        /** @var array $decoded */
        $decoded = json_decode((string) file_get_contents($final), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($uuid, $decoded['uuid'] ?? null);
        $this->assertNoStagingTemps();
    }

    /**
     * @throws Throwable
     */
    public function testWriteContainer_SuccessfulPublish_CreatesFinalJsonWithoutLeftoverTemps(): void
    {
        $uuid = 'container-uuid-1';
        $writer = new FileSystemResultsWriter($this->outputDirectory, new NullLogger());
        $writer->writeContainer(new ContainerResult($uuid));

        $final = $this->outputDirectory . DIRECTORY_SEPARATOR . $uuid . '-container.json';
        self::assertFileExists($final);
        /** @var array $decoded */
        $decoded = json_decode((string) file_get_contents($final), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($uuid, $decoded['uuid'] ?? null);
        $this->assertNoStagingTemps();
    }

    /**
     * @throws Throwable
     */
    public function testWriteGlobals_SuccessfulPublish_CreatesFinalJsonWithoutLeftoverTemps(): void
    {
        $uuid = 'globals-uuid-1';
        $writer = new FileSystemResultsWriter($this->outputDirectory, new NullLogger());
        $writer->writeGlobals(new Globals($uuid));

        $final = $this->outputDirectory . DIRECTORY_SEPARATOR . $uuid . '-globals.json';
        self::assertFileExists($final);
        /** @var array $decoded */
        $decoded = json_decode((string) file_get_contents($final), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($uuid, $decoded['uuid'] ?? null);
        $this->assertNoStagingTemps();
    }

    /**
     * @throws Throwable
     */
    public function testWriteAttachment_WithStringData_CreatesFinalFileWithoutLeftoverTemps(): void
    {
        $uuid = 'attachment-uuid-1';
        $writer = new FileSystemResultsWriter($this->outputDirectory, new NullLogger());
        $attachment = new AttachmentResult($uuid);
        $writer->writeAttachment($attachment, new StringDataSource('hello-attachment'));

        $final = $this->outputDirectory . DIRECTORY_SEPARATOR . $uuid . '-attachment';
        self::assertFileExists($final);
        self::assertSame('hello-attachment', file_get_contents($final));
        $this->assertNoStagingTemps();
    }

    /**
     * @throws Throwable
     */
    public function testWriteAttachment_OverwriteSameSourceTwice_LeavesLatestContentWithoutTemps(): void
    {
        $uuid = 'attachment-uuid-2';
        $writer = new FileSystemResultsWriter($this->outputDirectory, new NullLogger());
        $attachment = new AttachmentResult($uuid);
        $writer->writeAttachment($attachment, new StringDataSource('first'));
        $writer->writeAttachment($attachment, new StringDataSource('second-final'));

        $final = $this->outputDirectory . DIRECTORY_SEPARATOR . $uuid . '-attachment';
        self::assertFileExists($final);
        self::assertSame('second-final', file_get_contents($final));
        $this->assertNoStagingTemps();
    }

    /**
     * @throws Throwable
     */
    public function testWrite_SuccessfulPublish_InvokesSyncStreamOnce(): void
    {
        $writer = new class ($this->outputDirectory, new NullLogger()) extends FileSystemResultsWriter {
            public int $syncCalls = 0;

            protected function syncStream($stream): void
            {
                ++$this->syncCalls;
                parent::syncStream($stream);
            }
        };
        $writer->writeTest(new TestResult('sync-uuid-1'));

        self::assertSame(1, $writer->syncCalls);
        $this->assertNoStagingTemps();
    }

    /**
     * @throws Throwable
     */
    public function testWrite_FailedPublishAfterSync_KeepsStagedTemp(): void
    {
        $uuid = 'publish-fail-uuid';
        $writer = new class ($this->outputDirectory, new NullLogger()) extends FileSystemResultsWriter {
            protected function publishFile(string $tempFile, string $finalFile): void
            {
                throw new IoFailedException('simulated publish failure');
            }
        };

        try {
            $writer->writeTest(new TestResult($uuid));
            self::fail('Expected IoFailedException');
        } catch (IoFailedException $e) {
            self::assertStringContainsString('simulated publish failure', $e->getMessage());
        }

        $final = $this->outputDirectory . DIRECTORY_SEPARATOR . $uuid . '-result.json';
        self::assertFileDoesNotExist($final);

        $temps = $this->listStagingTemps('.allure-write-*.tmp');
        self::assertCount(1, $temps);
        /** @var array $decoded */
        $decoded = json_decode((string) file_get_contents($temps[0]), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($uuid, $decoded['uuid'] ?? null);
    }

    /**
     * @throws Throwable
     */
    public function testPublishFile_ReplaceFailsAndRestoreSucceeds_RestoresOldFinalAndKeepsTemp(): void
    {
        $uuid = 'restore-ok-uuid';
        $final = $this->outputDirectory . DIRECTORY_SEPARATOR . $uuid . '-result.json';
        file_put_contents($final, '{"uuid":"old"}');

        $writer = new class ($this->outputDirectory, new NullLogger()) extends FileSystemResultsWriter {
            private int $renameCalls = 0;

            protected function renamePath(string $from, string $to): bool
            {
                ++$this->renameCalls;
                // 1: temp→final (Windows-style fail while final exists)
                if (1 === $this->renameCalls) {
                    return false;
                }
                // 3: temp→final after aside — fail without moving
                if (3 === $this->renameCalls) {
                    return false;
                }
                // 2: final→aside, 4: aside→final restore
                return parent::renamePath($from, $to);
            }
        };

        try {
            $writer->writeTest(new TestResult($uuid));
            self::fail('Expected IoFailedException');
        } catch (IoFailedException $e) {
            self::assertStringContainsString('Failed to rename', $e->getMessage());
            self::assertStringNotContainsString('previous file left at', $e->getMessage());
        }

        self::assertFileExists($final);
        self::assertSame('{"uuid":"old"}', file_get_contents($final));

        $temps = $this->listStagingTemps('.allure-write-*.tmp');
        self::assertCount(1, $temps);
        /** @var array $decoded */
        $decoded = json_decode((string) file_get_contents($temps[0]), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($uuid, $decoded['uuid'] ?? null);
    }

    /**
     * @throws Throwable
     */
    public function testPublishFile_ReplaceAndRestoreBothFail_KeepsTempAndAside(): void
    {
        $uuid = 'restore-fail-uuid';
        $final = $this->outputDirectory . DIRECTORY_SEPARATOR . $uuid . '-result.json';
        file_put_contents($final, '{"uuid":"old"}');

        $writer = new class ($this->outputDirectory, new NullLogger()) extends FileSystemResultsWriter {
            private int $renameCalls = 0;

            protected function renamePath(string $from, string $to): bool
            {
                ++$this->renameCalls;
                // 1: temp→final fail (enter aside path)
                // 3: temp→final after aside fail
                // 4: aside→final restore fail
                if (in_array($this->renameCalls, [1, 3, 4], true)) {
                    return false;
                }
                // 2: final→aside
                return parent::renamePath($from, $to);
            }
        };

        try {
            $writer->writeTest(new TestResult($uuid));
            self::fail('Expected IoFailedException');
        } catch (IoFailedException $e) {
            self::assertStringContainsString('previous file left at', $e->getMessage());
        }

        self::assertFileDoesNotExist($final);

        $temps = $this->listStagingTemps('.allure-write-*.tmp');
        self::assertCount(2, $temps);

        $contents = array_map(
            static fn (string $path): string => (string) file_get_contents($path),
            $temps,
        );
        self::assertContains('{"uuid":"old"}', $contents);
        $newPayloads = array_filter(
            $contents,
            static fn (string $content): bool => false !== strpos($content, $uuid)
                && '{"uuid":"old"}' !== $content,
        );
        self::assertCount(1, $newPayloads);
    }

    private function assertNoStagingTemps(): void
    {
        self::assertSame([], $this->listStagingTemps('*.tmp'));
        self::assertSame([], $this->listStagingTemps('.allure-write-*'));
    }

    /**
     * @return list<string>
     */
    private function listStagingTemps(string $pattern): array
    {
        $matches = glob($this->outputDirectory . DIRECTORY_SEPARATOR . $pattern);
        if (false === $matches) {
            return [];
        }

        return $matches;
    }
}
