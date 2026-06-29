<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\File\Share\Integration;

use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\File\Share\Exceptions\InvalidConnectionStringException;
use AzureOss\Storage\File\Share\ShareServiceClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShareServiceClientTest extends TestCase
{
    #[Test]
    public function from_connection_string_with_file_endpoint_works(): void
    {
        $connectionString = 'DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;FileEndpoint=http://127.0.0.1:10003/devstoreaccount1;';
        $service = ShareServiceClient::fromConnectionString($connectionString);

        self::assertInstanceOf(StorageSharedKeyCredential::class, $service->credential);
        self::assertSame('devstoreaccount1', $service->credential->accountName);
        self::assertSame('http://127.0.0.1:10003/devstoreaccount1/', (string) $service->uri);
    }

    #[Test]
    public function from_connection_string_with_endpoint_suffix_works(): void
    {
        $connectionString = 'DefaultEndpointsProtocol=https;AccountName=testing;AccountKey=Y2hlZXNlMWNoZWVzZTEyY2hlZXNlMTIzCg==;EndpointSuffix=core.windows.net';
        $service = ShareServiceClient::fromConnectionString($connectionString);

        self::assertInstanceOf(StorageSharedKeyCredential::class, $service->credential);
        self::assertSame('https://testing.file.core.windows.net/', (string) $service->uri);
    }

    #[Test]
    public function from_connection_string_with_developer_shortcut_throws(): void
    {
        $this->expectException(InvalidConnectionStringException::class);

        ShareServiceClient::fromConnectionString('UseDevelopmentStorage=true');
    }

    #[Test]
    public function from_connection_string_with_sas_works(): void
    {
        $connectionString = 'FileEndpoint=https://storagesample.file.core.windows.net;SharedAccessSignature=sv=2026-06-06&sig=abc&spr=https&st=2026-06-29T10%3A00%3A00Z&se=2026-06-29T11%3A00%3A00Z&sr=s&sp=rl';
        $service = ShareServiceClient::fromConnectionString($connectionString);

        self::assertNull($service->credential);
        self::assertSame('https://storagesample.file.core.windows.net/?sv=2026-06-06&sig=abc&spr=https&st=2026-06-29T10%3A00%3A00Z&se=2026-06-29T11%3A00%3A00Z&sr=s&sp=rl', (string) $service->uri);
    }

    #[Test]
    public function from_connection_string_without_account_key_and_without_sas_throws(): void
    {
        $this->expectException(InvalidConnectionStringException::class);

        ShareServiceClient::fromConnectionString('FileEndpoint=http://127.0.0.1:10003/devstoreaccount1;');
    }

    #[Test]
    public function create_share_client_works(): void
    {
        $service = ShareServiceClient::fromConnectionString(
            'DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;FileEndpoint=http://127.0.0.1:10003/devstoreaccount1;',
        );
        $share = $service->getShareClient('testing');

        self::assertSame($service->credential, $share->credential);
        self::assertSame('http://127.0.0.1:10003/devstoreaccount1/testing', (string) $share->uri);
    }
}
