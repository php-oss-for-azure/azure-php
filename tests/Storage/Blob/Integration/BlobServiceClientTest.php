<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Blob\Integration;

use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Exceptions\BlobStorageException;
use AzureOss\Storage\Blob\Exceptions\InvalidConnectionStringException;
use AzureOss\Storage\Blob\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\Blob\Models\BlobContainer;
use AzureOss\Storage\Blob\Models\BlobContainerInclude;
use AzureOss\Storage\Blob\Models\BlobErrorCode;
use AzureOss\Storage\Blob\Models\GetBlobContainersOptions;
use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Sas\AccountSasBuilder;
use AzureOss\Storage\Common\Sas\AccountSasPermissions;
use AzureOss\Storage\Common\Sas\AccountSasResourceTypes;
use AzureOss\Tests\Storage\CreatesTempContainers;
use AzureOss\Tests\Storage\RetryableAssertions;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobServiceClientTest extends TestCase
{
    use CreatesTempContainers, RetryableAssertions;

    #[Test]
    public function from_connection_string_with_blob_endpoint_works(): void
    {
        $connectionString = 'DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://127.0.0.1:10000/devstoreaccount1;';
        $service = BlobServiceClient::fromConnectionString($connectionString);

        self::assertInstanceOf(StorageSharedKeyCredential::class, $service->credential);
        self::assertEquals('devstoreaccount1', $service->credential->accountName);
        self::assertEquals('Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==', $service->credential->accountKey);
        self::assertEquals('http://127.0.0.1:10000/devstoreaccount1/', (string) $service->uri);
    }

    #[Test]
    public function from_connection_string_with_endpoint_suffix_works(): void
    {
        $connectionString = 'DefaultEndpointsProtocol=https;AccountName=testing;AccountKey=Y2hlZXNlMWNoZWVzZTEyY2hlZXNlMTIzCg==;EndpointSuffix=core.windows.net';
        $service = BlobServiceClient::fromConnectionString($connectionString);

        self::assertInstanceOf(StorageSharedKeyCredential::class, $service->credential);
        self::assertEquals('testing', $service->credential->accountName);
        self::assertEquals('Y2hlZXNlMWNoZWVzZTEyY2hlZXNlMTIzCg==', $service->credential->accountKey);
        self::assertEquals('https://testing.blob.core.windows.net/', (string) $service->uri);
    }

    #[Test]
    public function from_connection_string_with_developer_shortcut_works(): void
    {
        $connectionString = 'UseDevelopmentStorage=true';
        $service = BlobServiceClient::fromConnectionString($connectionString);

        self::assertInstanceOf(StorageSharedKeyCredential::class, $service->credential);
        self::assertEquals('devstoreaccount1', $service->credential->accountName);
        self::assertEquals('Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==', $service->credential->accountKey);
        self::assertEquals('http://127.0.0.1:10000/devstoreaccount1/', (string) $service->uri);
    }

    #[Test]
    public function from_connection_string_without_account_name_throws(): void
    {
        $this->expectException(InvalidConnectionStringException::class);
        $connectionString = 'DefaultEndpointsProtocol=https;AccountKey=Y2hlZXNlMWNoZWVzZTEyY2hlZXNlMTIzCg==;EndpointSuffix=core.windows.net';
        BlobServiceClient::fromConnectionString($connectionString);
    }

    #[Test]
    public function from_connection_string_without_account_key_throws(): void
    {
        $this->expectException(InvalidConnectionStringException::class);
        $connectionString = 'DefaultEndpointsProtocol=https;AccountName=testing;EndpointSuffix=core.windows.net';
        BlobServiceClient::fromConnectionString($connectionString);
    }

    #[Test]
    public function from_connection_string_without_blob_endpoint_and_without_endpoint_suffix_throws(): void
    {
        $this->expectException(InvalidConnectionStringException::class);
        $connectionString = 'DefaultEndpointsProtocol=https;AccountName=testing;AccountKey=Y2hlZXNlMWNoZWVzZTEyY2hlZXNlMTIzCg==';
        BlobServiceClient::fromConnectionString($connectionString);
    }

    #[Test]
    public function from_connection_string_with_sas_works(): void
    {
        $connectionString = 'BlobEndpoint=https://storagesample.blob.core.windows.net;SharedAccessSignature=sv=2015-07-08&sig=iCvQmdZngZNW%2F4vw43j6%2BVz6fndHF5LI639QJba4r8o%3D&spr=https&st=2016-04-12T03%3A24%3A31Z&se=2016-04-13T03%3A29%3A31Z&srt=s&ss=bf&sp=rwl';
        $service = BlobServiceClient::fromConnectionString($connectionString);

        self::assertNull($service->credential);
        self::assertEquals('https://storagesample.blob.core.windows.net/?sv=2015-07-08&sig=iCvQmdZngZNW%2F4vw43j6%2BVz6fndHF5LI639QJba4r8o%3D&spr=https&st=2016-04-12T03%3A24%3A31Z&se=2016-04-13T03%3A29%3A31Z&srt=s&ss=bf&sp=rwl', (string) $service->uri);
    }

    #[Test]
    public function from_connection_string_without_account_key_and_without_sas_throws(): void
    {
        $this->expectException(InvalidConnectionStringException::class);
        $connectionString = 'BlobEndpoint=http://127.0.0.1:10000/devstoreaccount1;';
        BlobServiceClient::fromConnectionString($connectionString);
    }

    #[Test]
    public function from_connection_string_default_endpoint_protocol_overwrites_protocol_of_blob_endpoint(): void
    {
        $connectionString = 'DefaultEndpointsProtocol=https;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://127.0.0.1:10000/devstoreaccount1;';
        $service = BlobServiceClient::fromConnectionString($connectionString);

        self::assertEquals('https://127.0.0.1:10000/devstoreaccount1/', (string) $service->uri);
    }

    #[Test]
    public function create_container_client_works(): void
    {
        $connectionString = 'UseDevelopmentStorage=true';

        $service = BlobServiceClient::fromConnectionString($connectionString);

        $container = $service->getContainerClient('testing');

        self::assertEquals($service->credential, $container->credential);
        self::assertEquals('http://127.0.0.1:10000/devstoreaccount1/testing', (string) $container->uri);
    }

    #[Test]
    public function get_containers_works_with_prefix(): void
    {
        $prefix = 'test-prefixed-'.bin2hex(random_bytes(8)).'-';
        $first = $this->tempContainer($prefix);
        $second = $this->tempContainer($prefix);
        $this->tempContainer('test-unrelated-');

        $after = iterator_to_array($this->service()->getBlobContainers($prefix));

        self::assertCount(2, $after);
        self::assertEqualsCanonicalizing(
            [$first->containerName, $second->containerName],
            array_map(
                static fn (BlobContainer $item): string => $item->name,
                $after,
            ),
        );
    }

    #[Test]
    public function soft_deleted_container_can_be_listed_and_restored(): void
    {
        $service = $this->service(softDeletes: true);
        $container = $this->tempContainer(softDeletes: true);
        $containerName = $container->containerName;
        $container->delete();

        $deletedContainers = array_values(array_filter(
            iterator_to_array($service->getBlobContainers(
                prefix: $containerName,
                options: new GetBlobContainersOptions(includes: [BlobContainerInclude::DELETED]),
            )),
            static fn (BlobContainer $item): bool => $item->name === $containerName && $item->isDeleted,
        ));

        self::assertCount(1, $deletedContainers);
        self::assertNotNull($deletedContainers[0]->versionId);
        self::assertNotNull($deletedContainers[0]->properties->deletedOn);
        self::assertNotNull($deletedContainers[0]->properties->remainingRetentionDays);

        $restored = null;
        self::assertEventually(
            callback: function () use ($service, $containerName, $deletedContainers, &$restored): bool {
                try {
                    $restored ??= $service->undeleteBlobContainer($containerName, $deletedContainers[0]->versionId);

                    return $restored->exists();
                } catch (BlobStorageException $e) {
                    if ($e->errorCode === BlobErrorCode::ContainerBeingDeleted) {
                        return false;
                    }

                    throw $e;
                }
            },
            maxAttempts: 20,
            delayMs: 5000,
            message: 'Soft-deleted container restoration timed out',
        );
    }

    #[Test]
    public function find_blobs_by_tag_works(): void
    {
        $service = $this->service();
        $container = $service->getContainerClient('test-'.bin2hex(random_bytes(12)));
        $container->create();
        $this->tempContainers[] = $container;

        $uniqueTag = 'blobservice-'.bin2hex(random_bytes(8));
        $blob = $container->getBlobClient('tagged');
        $blob->upload('');
        $blob->setTags(['foo' => $uniqueTag]);

        self::assertEventually(
            fn () => count(iterator_to_array($service->findBlobsByTag("foo = '{$uniqueTag}'"))) === 1,
            message: 'Tag propagation timed out'
        );

        self::assertCount(0, iterator_to_array($service->findBlobsByTag("foo = 'noop'")));
    }

    #[Test]
    public function can_generate_account_sas_uri_works(): void
    {
        $containerClient = new BlobServiceClient(new Uri('https://testing.blob.core.windows.net'));

        self::assertFalse($containerClient->canGenerateAccountSasUri());

        $containerClient = new BlobServiceClient(
            new Uri('https://testing.blob.core.windows.net'),
            new StorageSharedKeyCredential('noop', 'noop'),
        );

        self::assertTrue($containerClient->canGenerateAccountSasUri());
    }

    #[Test]
    public function generate_account_sas_uri_builds_an_account_sas_uri(): void
    {
        $sas = (new BlobServiceClient(
            new Uri('https://account.blob.core.windows.net/'),
            new StorageSharedKeyCredential('account', base64_encode(str_repeat('x', 32))),
        ))->generateAccountSasUri(
            AccountSasBuilder::new()
                ->setPermissions(new AccountSasPermissions(list: true))
                ->setResourceTypes(new AccountSasResourceTypes(service: true))
                ->setVersion(ApiVersion::latestGA()->value)
                ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z')),
        );

        parse_str($sas->getQuery(), $query);

        self::assertSame('b', $query['ss'] ?? null);
        self::assertSame('s', $query['srt'] ?? null);
        self::assertSame('l', $query['sp'] ?? null);
        self::assertSame(ApiVersion::latestGA()->value, $query['sv'] ?? null);
        self::assertArrayHasKey('sig', $query);
    }

    #[Test]
    public function generate_account_sas_throws_when_there_are_no_shared_key_credential(): void
    {
        $this->expectException(UnableToGenerateSasException::class);

        $serviceClientWithoutCredential = new BlobServiceClient(new Uri('example.com'));

        $serviceClientWithoutCredential->generateAccountSasUri(
            AccountSasBuilder::new(),
        );
    }
}
