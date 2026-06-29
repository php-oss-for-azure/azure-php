<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\BlobLaravel\Unit;

use AzureOss\Storage\BlobLaravel\AzureStorageBlobAdapter;
use AzureOss\Storage\BlobLaravel\AzureStorageBlobServiceProvider;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AzureStorageBlobServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AzureStorageBlobServiceProvider::class];
    }

    #[Test]
    public function it_registers_the_driver(): void
    {
        config(['filesystems.disks.azure' => [
            'driver' => 'azure-storage-blob',
            'credential' => 'managed_identity',
            'endpoint' => 'https://test.blob.core.windows.net',
            'container' => 'test-container',
        ]]);

        self::assertInstanceOf(AzureStorageBlobAdapter::class, Storage::disk('azure'));
    }
}
