<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\File\Share\Integration;

use AzureOss\Tests\Storage\CreatesTempMountedShareDirectories;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MountedFileShareTest extends TestCase
{
    use CreatesTempMountedShareDirectories;

    #[Test]
    public function mounted_file_share_supports_creating_a_directory_and_file(): void
    {
        $root = $this->tempMountedShareDirectory();
        $directory = $root['absolutePath'].'/nested';
        $filePath = $directory.'/hello.txt';
        $contents = 'Azure Files mounted share smoke test';

        self::assertTrue(mkdir($directory, 0777, true));
        self::assertDirectoryExists($directory);

        $written = file_put_contents($filePath, $contents);

        self::assertNotFalse($written);
        self::assertSame(strlen($contents), $written);
        self::assertFileExists($filePath);
        self::assertSame($contents, file_get_contents($filePath));
    }
}
