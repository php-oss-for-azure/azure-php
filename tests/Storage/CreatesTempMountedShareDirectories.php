<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage;

use AzureOss\Tests\RequiresEnvironmentVariables;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

/** @mixin TestCase */
trait CreatesTempMountedShareDirectories
{
    use RequiresEnvironmentVariables;

    /** @var list<string> */
    private array $tempMountedShareDirectories = [];

    /**
     * @return array{relativePath: string, absolutePath: string}
     */
    protected function tempMountedShareDirectory(string $prefix = 'test-'): array
    {
        $mountPath = self::getRequiredEnvironmentVariable('AZURE_STORAGE_FILE_SHARE_PATH');

        $relativePath = $prefix.bin2hex(random_bytes(12));
        $absolutePath = rtrim($mountPath, '/').'/'.$relativePath;

        if (! mkdir($absolutePath, 0777, true) && ! is_dir($absolutePath)) {
            throw new \RuntimeException("Failed to create temporary mounted share directory: {$absolutePath}");
        }

        $this->tempMountedShareDirectories[] = $absolutePath;

        return [
            'relativePath' => $relativePath,
            'absolutePath' => $absolutePath,
        ];
    }

    #[After]
    protected function cleanupTempMountedShareDirectories(): void
    {
        foreach (array_reverse($this->tempMountedShareDirectories) as $path) {
            $this->deleteDirectoryRecursively($path);
        }

        $this->tempMountedShareDirectories = [];
    }

    private function deleteDirectoryRecursively(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if (! $item instanceof \SplFileInfo) {
                continue;
            }

            try {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            } catch (\Throwable) {
            }
        }

        try {
            @rmdir($path);
        } catch (\Throwable) {
        }
    }
}
