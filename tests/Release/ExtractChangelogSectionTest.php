<?php

declare(strict_types=1);

namespace AzureOss\Tests\Release;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExtractChangelogSectionTest extends TestCase
{
    #[DataProvider('supportedHeadingFormats')]
    #[Test]
    public function it_extracts_the_requested_version_section(string $heading): void
    {
        $changelogPath = $this->createTempChangelog(<<<MARKDOWN
            # Changelog

            ## Unreleased

            Pending work.

            {$heading}

            ### Added

            - First line.
            - Second line.

            ## 9.9.9

            Old release.
            MARKDOWN);

        [$exitCode, $stdout, $stderr] = $this->runScript($changelogPath, '1.2.3');

        self::assertSame(0, $exitCode, $stderr);
        self::assertSame("### Added\n\n- First line.\n- Second line.\n", $stdout);
        self::assertSame('', $stderr);
    }

    public static function supportedHeadingFormats(): \Generator
    {
        yield 'bracketed heading with date' => ['## [1.2.3] - 2026-06-27'];
        yield 'plain heading with date' => ['## 1.2.3 - 2026-06-27'];
        yield 'bracketed heading' => ['## [1.2.3]'];
        yield 'plain heading' => ['## 1.2.3'];
    }

    #[Test]
    public function it_fails_when_the_changelog_file_is_missing(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runScript('/tmp/does-not-exist-'.bin2hex(random_bytes(8)).'.md', '1.2.3');

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Changelog file does not exist', $stderr);
    }

    #[Test]
    public function it_fails_when_the_requested_version_is_missing(): void
    {
        $changelogPath = $this->createTempChangelog(<<<'MARKDOWN'
            # Changelog

            ## Unreleased

            Pending work.
            MARKDOWN);

        [$exitCode, $stdout, $stderr] = $this->runScript($changelogPath, '1.2.3');

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Version section not found', $stderr);
    }

    #[Test]
    public function it_fails_when_the_requested_version_section_is_empty(): void
    {
        $changelogPath = $this->createTempChangelog(<<<'MARKDOWN'
            # Changelog

            ## 1.2.3

            ## 1.2.2

            Previous release.
            MARKDOWN);

        [$exitCode, $stdout, $stderr] = $this->runScript($changelogPath, '1.2.3');

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Version section is empty', $stderr);
    }

    private function createTempChangelog(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'changelog-');

        self::assertNotFalse($path);
        self::assertNotFalse(file_put_contents($path, $contents));

        $this->addToAssertionCount(1);

        return $path;
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    private function runScript(string $changelogPath, string $version): array
    {
        $command = [
            PHP_BINARY,
            getcwd().'/tools/release/extract-changelog-section.php',
            $changelogPath,
            $version,
        ];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, getcwd());

        self::assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, $stdout === false ? '' : $stdout, $stderr === false ? '' : $stderr];
    }
}
