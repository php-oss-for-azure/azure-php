<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Blob\Unit;

use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Models\BlobContainerClientOptions;
use AzureOss\Storage\Blob\Models\BlobServiceClientOptions;
use AzureOss\Storage\Blob\Specialized\BlobBatchClient;
use AzureOss\Storage\Common\Middleware\HttpClientOptions;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobBatchClientOptionsTest extends TestCase
{
    #[Test]
    public function container_batch_client_inherits_parent_http_options(): void
    {
        $container = new BlobContainerClient(
            new Uri('https://example.com/container'),
            options: new BlobContainerClientOptions($this->httpClientOptions()),
        );
        $batch = $container->getBlobBatchClient();

        self::assertSame('container', $batch->containerName);
        $this->assertHttpClientOptions($batch);
    }

    #[Test]
    public function service_batch_client_inherits_parent_http_options(): void
    {
        $service = new BlobServiceClient(
            new Uri('https://example.com'),
            options: new BlobServiceClientOptions($this->httpClientOptions()),
        );
        $batch = $service->getBlobBatchClient();

        self::assertNull($batch->containerName);
        $this->assertHttpClientOptions($batch);
    }

    private function httpClientOptions(): HttpClientOptions
    {
        return new HttpClientOptions(
            timeout: 123,
            connectTimeout: 45,
            verifySsl: false,
        );
    }

    private function assertHttpClientOptions(BlobBatchClient $batchClient): void
    {
        $clientProperty = new \ReflectionProperty($batchClient, 'client');
        $client = $clientProperty->getValue($batchClient);

        self::assertInstanceOf(Client::class, $client);

        $configProperty = new \ReflectionProperty($client, 'config');
        $config = $configProperty->getValue($client);

        self::assertIsArray($config);
        self::assertSame(123, $config['timeout']);
        self::assertSame(45, $config['connect_timeout']);
        self::assertFalse($config['verify']);
    }
}
