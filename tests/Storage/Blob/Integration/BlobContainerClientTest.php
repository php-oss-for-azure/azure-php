<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Blob\Integration;

use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Exceptions\BlobStorageException;
use AzureOss\Storage\Blob\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\Blob\Models\Blob;
use AzureOss\Storage\Blob\Models\BlobErrorCode;
use AzureOss\Storage\Blob\Models\BlobPrefix;
use AzureOss\Storage\Blob\Models\CreateContainerOptions;
use AzureOss\Storage\Blob\Models\GetBlobsOptions;
use AzureOss\Storage\Blob\Models\PublicAccessType;
use AzureOss\Storage\Blob\Sas\BlobContainerSasPermissions;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use AzureOss\Storage\Blob\Sas\BlobSasPermissions;
use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Tests\Storage\CreatesTempContainers;
use AzureOss\Tests\Storage\RetryableAssertions;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobContainerClientTest extends TestCase
{
    use CreatesTempContainers, RetryableAssertions;

    #[Test]
    public function create_blob_client_works(): void
    {
        $connectionString = 'UseDevelopmentStorage=true';

        $service = BlobServiceClient::fromConnectionString($connectionString);

        $container = $service->getContainerClient('testing');
        $blob = $container->getBlobClient('some/file.txt');

        self::assertEquals($blob->credential, $container->credential);
        self::assertEquals('http://127.0.0.1:10000/devstoreaccount1/testing/some/file.txt', (string) $blob->uri);
    }

    #[Test]
    public function create_blob_clients_works_with_leading_slash_in_blob_name(): void
    {
        $connectionString = 'UseDevelopmentStorage=true';

        $service = BlobServiceClient::fromConnectionString($connectionString);

        $container = $service->getContainerClient('testing');
        $blob = $container->getBlobClient('/some/file.txt');

        self::assertEquals($blob->credential, $container->credential);
        self::assertEquals('http://127.0.0.1:10000/devstoreaccount1/testing/some/file.txt', (string) $blob->uri);
    }

    #[Test]
    public function create_works(): void
    {
        $containerName = 'test-'.uniqid();
        $container = $this->service()->getContainerClient($containerName);

        self::assertFalse($container->exists());

        $container->create();

        self::assertTrue($container->exists());

        $container->delete();
    }

    #[Test]
    public function create_throws_when_container_already_exists(): void
    {
        $container = $this->tempContainer();

        self::assertBlobStorageException(BlobErrorCode::ContainerAlreadyExists, fn () => $container->create());
    }

    #[Test]
    public function create_works_for_public_access_type_blob(): void
    {
        $containerName = 'test-'.uniqid();
        $container = $this->service(public: true)->getContainerClient($containerName);

        self::assertFalse($container->exists());

        $container->create(new CreateContainerOptions(publicAccessType: PublicAccessType::BLOB));

        self::assertTrue($container->exists());

        // add a file that should be publicly accessible
        $blob = $container->getBlobClient('file.txt');
        $blob->upload('test');

        $blobWithoutAuth = new BlobClient($blob->uri);
        $blobWithoutAuth->getProperties(); // should not throw

        $container->delete();
    }

    #[Test]
    public function create_works_for_public_access_type_container(): void
    {
        $containerName = 'test-'.uniqid();
        $container = $this->service(public: true)->getContainerClient($containerName);

        self::assertFalse($container->exists());

        $container->create(new CreateContainerOptions(publicAccessType: PublicAccessType::CONTAINER));

        self::assertTrue($container->exists());

        $containerWithoutAuth = new BlobContainerClient($container->uri);

        $containerWithoutAuth->getProperties(); // should not throw

        $container->delete();
    }

    #[Test]
    public function create_if_not_exists_works(): void
    {
        $containerName = 'test-'.uniqid();
        $container = $this->service()->getContainerClient($containerName);

        self::assertFalse($container->exists());

        $container->createIfNotExists();

        self::assertTrue($container->exists());

        $container->delete();
    }

    #[Test]
    public function create_if_not_exists_doesnt_throw_when_container_already_exists(): void
    {
        $this->expectNotToPerformAssertions();

        $container = $this->tempContainer();
        $container->createIfNotExists();
    }

    #[Test]
    public function delete_works(): void
    {
        $container = $this->tempContainer();

        self::assertTrue($container->exists());

        $container->delete();

        self::assertFalse($container->exists());
    }

    #[Test]
    public function delete_throws_when_container_doesnt_exists(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->delete(),
        );
    }

    #[Test]
    public function delete_if_exists_works(): void
    {
        $container = $this->tempContainer();

        self::assertTrue($container->exists());

        $container->deleteIfExists();

        self::assertFalse($container->exists());
    }

    #[Test]
    public function delete_if_exists_doesnt_throw_when_container_doesnt_exists(): void
    {
        $this->expectNotToPerformAssertions();

        $this->service()->getContainerClient('noop')->deleteIfExists();
    }

    #[Test]
    public function exists_works(): void
    {
        $containerName = 'test-'.uniqid();
        $container = $this->service()->getContainerClient($containerName);

        self::assertFalse($container->exists());

        $container->create();

        self::assertTrue($container->exists());

        $container->delete();

        self::assertFalse($container->exists());
    }

    #[Test]
    public function get_blobs_works(): void
    {
        $container = $this->tempContainer();
        $container->getBlobClient('fileA.txt')->upload('test');
        $container->getBlobClient('fileB.txt')->upload('test');
        $container->getBlobClient('some/fileB.txt')->upload('test');
        $container->getBlobClient('some/deeply/nested/fileB.txt')->upload('test');

        $blobs = iterator_to_array($container->getBlobs());

        self::assertCount(4, $blobs);
    }

    #[Test]
    public function get_blobs_works_with_prefix(): void
    {
        $container = $this->tempContainer();
        $container->getBlobClient('fileA.txt')->upload('test');
        $container->getBlobClient('fileB.txt')->upload('test');
        $container->getBlobClient('some/fileB.txt')->upload('test');
        $container->getBlobClient('some/deeply/nested/fileB.txt')->upload('test');

        $blobs = iterator_to_array($container->getBlobs('some/'));

        self::assertCount(2, $blobs);
    }

    #[Test]
    public function get_blobs_works_with_max_results(): void
    {
        $container = $this->tempContainer();
        $container->getBlobClient('fileA.txt')->upload('test');
        $container->getBlobClient('fileB.txt')->upload('test');
        $container->getBlobClient('some/fileB.txt')->upload('test');
        $container->getBlobClient('some/deeply/nested/fileB.txt')->upload('test');

        $blobs = iterator_to_array($container->getBlobs(options: new GetBlobsOptions(pageSize: 2)));

        self::assertCount(4, $blobs);
    }

    #[Test]
    public function get_blobs_throws_if_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => iterator_to_array($this->service()->getContainerClient('noop')->getBlobs()),
        );
    }

    #[Test]
    public function get_blobs_by_hierarchy_works(): void
    {
        $container = $this->tempContainer();
        $container->getBlobClient('fileA.txt')->upload('test');
        $container->getBlobClient('fileB.txt')->upload('test');
        $container->getBlobClient('some/fileB.txt')->upload('test');
        $container->getBlobClient('some/deeply/nested/fileB.txt')->upload('test');

        $results = iterator_to_array($container->getBlobsByHierarchy());

        $blobs = array_filter($results, fn ($item) => $item instanceof Blob);
        $prefixes = array_filter($results, fn ($item) => $item instanceof BlobPrefix);

        self::assertCount(2, $blobs);
        self::assertCount(1, $prefixes);
    }

    #[Test]
    public function get_blobs_by_hierarchy_works_with_prefix(): void
    {
        $container = $this->tempContainer();
        $container->getBlobClient('fileA.txt')->upload('test');
        $container->getBlobClient('fileB.txt')->upload('test');
        $container->getBlobClient('some/fileB.txt')->upload('test');
        $container->getBlobClient('some/deeply/nested/fileB.txt')->upload('test');

        $results = iterator_to_array($container->getBlobsByHierarchy('some/'));

        $blobs = array_filter($results, fn ($item) => $item instanceof Blob);
        $prefixes = array_filter($results, fn ($item) => $item instanceof BlobPrefix);

        self::assertCount(1, $blobs);
        self::assertCount(1, $prefixes);
    }

    #[Test]
    public function get_blobs_by_hierarchy_works_with_max_results(): void
    {
        $container = $this->tempContainer();
        $container->getBlobClient('fileA.txt')->upload('test');
        $container->getBlobClient('fileB.txt')->upload('test');
        $container->getBlobClient('some/fileB.txt')->upload('test');
        $container->getBlobClient('some/deeply/nested/fileB.txt')->upload('test');

        $results = iterator_to_array($container->getBlobsByHierarchy(options: new GetBlobsOptions(pageSize: 2)));

        $blobs = array_filter($results, fn ($item) => $item instanceof Blob);
        $prefixes = array_filter($results, fn ($item) => $item instanceof BlobPrefix);

        self::assertCount(2, $blobs);
        self::assertCount(1, $prefixes);
    }

    #[Test]
    public function get_blobs_by_hierarchy_works_with_different_delimiter(): void
    {
        $container = $this->tempContainer();
        $container->getBlobClient('fileA.txt')->upload('test');
        $container->getBlobClient('fileB.txt')->upload('test');
        $container->getBlobClient('some-fileB.txt')->upload('test');
        $container->getBlobClient('some-deeply-nested-fileB.txt')->upload('test');

        $results = iterator_to_array($container->getBlobsByHierarchy(delimiter: '-'));

        $blobs = array_filter($results, fn ($item) => $item instanceof Blob);
        $prefixes = array_filter($results, fn ($item) => $item instanceof BlobPrefix);

        self::assertCount(2, $blobs);
        self::assertCount(1, $prefixes);
    }

    #[Test]
    public function get_blobs_by_hierarchy_throws_if_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => iterator_to_array($this->service()->getContainerClient('noop')->getBlobsByHierarchy()),
        );
    }

    #[Test]
    public function can_generate_sas_uri_works(): void
    {
        $container = new BlobContainerClient(new Uri('https://testing.blob.core.windows.net/testing'));

        self::assertFalse($container->canGenerateSasUri());

        $container = new BlobContainerClient(
            new Uri('https://testing.blob.core.windows.net/testing'),
            new StorageSharedKeyCredential('noop', 'noop'),
        );

        self::assertTrue($container->canGenerateSasUri());
    }

    #[Test]
    public function generate_sas_uri_builds_a_container_sas_uri(): void
    {
        $container = new BlobContainerClient(
            new Uri('https://account.blob.core.windows.net/container?custom=value'),
            new StorageSharedKeyCredential('account', base64_encode(str_repeat('x', 32))),
        );

        $sas = $container->generateSasUri(
            BlobSasBuilder::new()
                ->setPermissions(new BlobContainerSasPermissions(list: true))
                ->setVersion(ApiVersion::latestGA()->value)
                ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z')),
        );

        parse_str($sas->getQuery(), $query);

        self::assertSame('c', $query['sr'] ?? null);
        self::assertSame('l', $query['sp'] ?? null);
        self::assertSame(ApiVersion::latestGA()->value, $query['sv'] ?? null);
        self::assertSame('value', $query['custom'] ?? null);
        self::assertArrayHasKey('sig', $query);
    }

    #[Test]
    public function generate_sas_uri_clears_blob_specific_state_from_a_reused_builder(): void
    {
        $credential = new StorageSharedKeyCredential('account', base64_encode(str_repeat('x', 32)));
        $builder = BlobSasBuilder::new()
            ->setPermissions(new BlobSasPermissions(read: true))
            ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z'));

        (new BlobClient(new Uri('https://account.blob.core.windows.net/container/blob?snapshot=2026-06-28T10%3A20%3A30.1234567Z'), $credential))
            ->generateSasUri($builder);

        $sas = (new BlobContainerClient(new Uri('https://account.blob.core.windows.net/container'), $credential))
            ->generateSasUri($builder->setPermissions(new BlobContainerSasPermissions(list: true)));

        parse_str($sas->getQuery(), $query);

        self::assertSame('c', $query['sr'] ?? null);
        self::assertSame('l', $query['sp'] ?? null);
    }

    #[Test]
    public function generate_sas_uri_throws_when_there_are_no_shared_key_credential(): void
    {
        $this->expectException(UnableToGenerateSasException::class);

        $containerWithoutCredential = new BlobContainerClient(new Uri('example.com'));

        $containerWithoutCredential->generateSasUri(
            BlobSasBuilder::new(),
        );
    }

    #[Test]
    public function get_properties_works(): void
    {
        $this->expectNotToPerformAssertions();

        $container = $this->tempContainer();
        $container->getProperties();
    }

    #[Test]
    public function get_properties_throws_when_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->getProperties(),
        );
    }

    #[Test]
    public function set_metadata_works(): void
    {
        $container = $this->tempContainer();
        $container->setMetadata([
            'foo' => 'bar',
            'baz' => 'qux',
        ]);

        $properties = $container->getProperties();

        self::assertEquals('bar', $properties->metadata['foo']);
        self::assertEquals('qux', $properties->metadata['baz']);
    }

    #[Test]
    public function set_metadata_throws_when_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->setMetadata([]),
        );
    }

    #[Test]
    public function find_blobs_by_tag_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('tagged');

        $blob->upload('');
        $blob->setTags(['foo' => 'bar']);

        self::assertEventually(
            fn () => count(iterator_to_array($container->findBlobsByTag("foo = 'bar'"))) === 1,
            message: 'Tag propagation timed out'
        );

        self::assertCount(0, iterator_to_array($container->findBlobsByTag("foo = 'noop'")));
    }

    #[Test]
    public function find_blobs_by_tag_works_throws_when_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => iterator_to_array($this->service()->getContainerClient('noop')->findBlobsByTag("foo = 'bar'")),
        );
    }

    private static function assertBlobStorageException(BlobErrorCode $errorCode, callable $callback): void
    {
        try {
            $callback();

            self::fail(sprintf('Expected %s with error code %s.', BlobStorageException::class, $errorCode->value));
        } catch (BlobStorageException $e) {
            self::assertSame($errorCode, $e->errorCode);
        }
    }
}
