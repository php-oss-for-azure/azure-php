<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\File\Share\Feature;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class MountedFileShareTest extends TestCase
{
    #[Test]
    public function mounted_file_share_supports_creating_a_directory_and_file(): void
    {
        $mountPath = getenv('AZURE_STORAGE_FILE_SHARE_PATH');

        if ($mountPath === false || $mountPath === '') {
            self::markTestSkipped('Missing environment variable: AZURE_STORAGE_FILE_SHARE_PATH');
        }

        $root = rtrim($mountPath, '/').'/test-'.bin2hex(random_bytes(12));
        $directory = $root.'/nested';
        $filePath = $directory.'/hello.txt';
        $contents = 'Azure Files mounted share smoke test';

        try {
            self::assertTrue(mkdir($directory, 0777, true));
            self::assertDirectoryExists($directory);

            $written = file_put_contents($filePath, $contents);

            self::assertNotFalse($written);
            self::assertSame(strlen($contents), $written);
            self::assertFileExists($filePath);
            self::assertSame($contents, file_get_contents($filePath));
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());

                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
