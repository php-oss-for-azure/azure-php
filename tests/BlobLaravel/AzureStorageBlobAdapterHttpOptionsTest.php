<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\BlobLaravel;

use AzureOss\Storage\BlobLaravel\AzureStorageBlobAdapter;
use AzureOss\Storage\BlobLaravel\AzureStorageBlobServiceProvider;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AzureStorageBlobAdapterHttpOptionsTest extends TestCase
{
    /**
     * A well-formed (Azurite) connection string: the disk resolves without any
     * network call, so these tests only exercise config validation and adapter
     * wiring of the new HTTP options, not real Azure I/O.
     */
    private const CONNECTION_STRING = 'DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://127.0.0.1:10000/devstoreaccount1;';

    protected function getPackageProviders($app): array
    {
        return [AzureStorageBlobServiceProvider::class];
    }

    #[Test]
    public function it_resolves_the_disk_when_http_client_options_are_configured(): void
    {
        config(['filesystems.disks.azure' => [
            'driver' => 'azure-storage-blob',
            'connection_string' => self::CONNECTION_STRING,
            'container' => 'noop',
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify_ssl' => false,
        ]]);

        self::assertInstanceOf(AzureStorageBlobAdapter::class, Storage::disk('azure'));
    }

    #[Test]
    public function it_rejects_a_non_integer_timeout(): void
    {
        config(['filesystems.disks.azure' => [
            'driver' => 'azure-storage-blob',
            'connection_string' => self::CONNECTION_STRING,
            'container' => 'noop',
            'timeout' => '30',
        ]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[timeout]');

        Storage::disk('azure');
    }

    #[Test]
    public function it_rejects_a_non_boolean_verify_ssl(): void
    {
        config(['filesystems.disks.azure' => [
            'driver' => 'azure-storage-blob',
            'connection_string' => self::CONNECTION_STRING,
            'container' => 'noop',
            'verify_ssl' => 'yes',
        ]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[verify_ssl]');

        Storage::disk('azure');
    }
}
