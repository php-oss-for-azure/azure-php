<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Blob\Integration;

use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Exceptions\BlobBatchException;
use AzureOss\Storage\Blob\Models\BlobErrorCode;
use AzureOss\Storage\Blob\Models\BlobInclude;
use AzureOss\Storage\Blob\Models\DeleteBlobOptions;
use AzureOss\Storage\Blob\Models\DeleteSnapshotsOption;
use AzureOss\Storage\Blob\Models\GetBlobsOptions;
use AzureOss\Storage\Blob\Sas\BlobContainerSasPermissions;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Sas\AccountSasBuilder;
use AzureOss\Storage\Common\Sas\AccountSasPermissions;
use AzureOss\Storage\Common\Sas\AccountSasResourceTypes;
use AzureOss\Tests\Storage\CreatesTempContainers;
use AzureOss\Tests\Storage\RetryableAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobBatchClientTest extends TestCase
{
    use CreatesTempContainers, RetryableAssertions;

    #[Test]
    public function delete_blobs_deletes_blob_names(): void
    {
        $container = $this->tempContainer();
        $names = $this->uploadBlobs($container, ['a.txt', 'b.txt', 'nested/c.txt']);

        $container->getBlobBatchClient()->deleteBlobs($names);

        self::assertSame([], $this->blobNames($container));
    }

    #[Test]
    public function delete_blobs_deletes_blob_clients(): void
    {
        $container = $this->tempContainer();
        $this->uploadBlobs($container, ['a.txt', 'b.txt']);

        $container->getBlobBatchClient()->deleteBlobs([
            $container->getBlobClient('a.txt'),
            $container->getBlobClient('b.txt'),
        ]);

        self::assertSame([], $this->blobNames($container));
    }

    #[Test]
    public function service_batch_client_deletes_blobs_across_containers(): void
    {
        $first = $this->tempContainer();
        $second = $this->tempContainer();
        $this->uploadBlobs($first, ['a.txt']);
        $this->uploadBlobs($second, ['b.txt']);

        $this->service()->getBlobBatchClient()->deleteBlobs([
            $first->containerName.'/a.txt',
            $second->getBlobClient('b.txt'),
        ]);

        self::assertSame([], $this->blobNames($first));
        self::assertSame([], $this->blobNames($second));
    }

    #[Test]
    public function delete_blobs_encodes_blob_names(): void
    {
        $container = $this->tempContainer();
        $names = $this->uploadBlobs($container, ['with space.txt', 'ünïcødé/файл.txt', 'a+b.txt', 'emoji-🙂.txt']);

        $container->getBlobBatchClient()->deleteBlobs($names);

        self::assertSame([], $this->blobNames($container));
    }

    #[Test]
    public function delete_blobs_reports_missing_blob(): void
    {
        $container = $this->tempContainer();
        $this->uploadBlobs($container, ['a.txt', 'c.txt']);

        try {
            $container->getBlobBatchClient()->deleteBlobs(['a.txt', 'missing.txt', 'c.txt']);

            self::fail('Expected deleting a missing blob to fail.');
        } catch (BlobBatchException $e) {
            self::assertSame([1], array_column($e->failures, 'index'));
            self::assertSame(['missing.txt'], array_column($e->failures, 'blob'));
            self::assertSame(BlobErrorCode::BlobNotFound, $e->failures[0]->exception->errorCode);
        }

        self::assertSame([], $this->blobNames($container));
    }

    #[Test]
    public function delete_blobs_reports_leased_blob(): void
    {
        $container = $this->tempContainer();
        $names = $this->uploadBlobs($container, ['a.txt', 'leased.txt', 'c.txt']);
        $container->getBlobClient('leased.txt')->getBlobLeaseClient()->acquire();

        try {
            $container->getBlobBatchClient()->deleteBlobs($names);

            self::fail('Expected deleting a leased blob without lease ID to fail.');
        } catch (BlobBatchException $e) {
            self::assertSame([1], array_column($e->failures, 'index'));
            self::assertSame(['leased.txt'], array_column($e->failures, 'blob'));
            self::assertSame(BlobErrorCode::LeaseIdMissing, $e->failures[0]->exception->errorCode);
        }

        self::assertSame(['leased.txt'], $this->blobNames($container));
    }

    #[Test]
    public function delete_blobs_sends_snapshot_option(): void
    {
        $container = $this->tempContainer();
        $names = $this->uploadBlobs($container, ['a.txt', 'b.txt']);
        foreach ($names as $name) {
            $container->getBlobClient($name)->createSnapshot();
        }

        $container->getBlobBatchClient()->deleteBlobs($names, DeleteSnapshotsOption::INCLUDE_SNAPSHOTS);

        self::assertSame([], $this->blobNames($container, [BlobInclude::SNAPSHOTS]));
    }

    #[Test]
    public function submit_batch_sends_snapshot_option_per_blob(): void
    {
        $container = $this->tempContainer();
        $this->uploadBlobs($container, ['a.txt', 'b.txt', 'c.txt']);
        $container->getBlobClient('a.txt')->createSnapshot();
        $container->getBlobClient('b.txt')->createSnapshot();

        $batchClient = $container->getBlobBatchClient();
        $batch = $batchClient->createBatch();
        $batch->deleteBlob('a.txt', new DeleteBlobOptions(snapshotsOption: DeleteSnapshotsOption::INCLUDE_SNAPSHOTS));
        $batch->deleteBlob($container->getBlobClient('b.txt'), new DeleteBlobOptions(snapshotsOption: DeleteSnapshotsOption::ONLY_SNAPSHOTS));
        $batch->deleteBlob('c.txt');

        $batchClient->submitBatch($batch);

        self::assertSame(['b.txt'], $this->blobNames($container, [BlobInclude::SNAPSHOTS]));
    }

    #[Test]
    public function delete_blobs_deletes_selected_snapshot(): void
    {
        $container = $this->tempContainer();
        $this->uploadBlobs($container, ['a.txt']);
        $blob = $container->getBlobClient('a.txt');
        $snapshot = $blob->createSnapshot()->snapshot;

        $container->getBlobBatchClient()->deleteBlobs([$blob->withSnapshot($snapshot)]);

        self::assertSame(['a.txt'], $this->blobNames($container, [BlobInclude::SNAPSHOTS]));
    }

    #[Test]
    public function container_sas_batch_client_deletes_blobs(): void
    {
        $container = $this->tempContainer();
        $names = $this->uploadBlobs($container, ['a.txt', 'with space.txt']);
        $sasUri = static fn (BlobContainerSasPermissions $permissions) => $container->generateSasUri(
            BlobSasBuilder::new()
                ->setPermissions($permissions)
                ->setVersion(ApiVersion::latestGA()->value)
                ->setExpiresOn(new \DateTimeImmutable('+1 hour')),
        );
        $readContainer = new BlobContainerClient($sasUri(new BlobContainerSasPermissions(read: true)));

        // Azure can transiently reject signed requests while the SAS becomes available.
        self::assertEventuallySucceeds(
            callback: static fn () => $readContainer->getBlobClient('a.txt')->getProperties(),
            maxAttempts: 30,
        );

        (new BlobContainerClient($sasUri(new BlobContainerSasPermissions(write: true, delete: true))))->getBlobBatchClient()->deleteBlobs($names);

        self::assertSame([], $this->blobNames($container));
    }

    #[Test]
    public function account_sas_batch_client_deletes_blobs(): void
    {
        $container = $this->tempContainer();
        $this->uploadBlobs($container, ['a.txt']);
        $sasUri = fn (AccountSasPermissions $permissions) => $this->service()->generateAccountSasUri(
            AccountSasBuilder::new()
                ->setPermissions($permissions)
                ->setResourceTypes(new AccountSasResourceTypes(service: true, container: true, object: true))
                ->setVersion(ApiVersion::latestGA()->value)
                ->setExpiresOn(new \DateTimeImmutable('+1 hour')),
        );
        $readService = new BlobServiceClient($sasUri(new AccountSasPermissions(read: true)));

        // Azure can transiently reject signed requests while the SAS becomes available.
        self::assertEventuallySucceeds(
            callback: static fn () => $readService->getContainerClient($container->containerName)->getBlobClient('a.txt')->getProperties(),
            maxAttempts: 30,
        );

        (new BlobServiceClient($sasUri(new AccountSasPermissions(write: true, delete: true))))->getBlobBatchClient()->deleteBlobs([$container->containerName.'/a.txt']);

        self::assertSame([], $this->blobNames($container));
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function uploadBlobs(BlobContainerClient $container, array $names): array
    {
        foreach ($names as $name) {
            $container->getBlobClient($name)->upload('content');
        }

        return $names;
    }

    /**
     * @param  list<BlobInclude>  $includes
     * @return list<string>
     */
    private function blobNames(BlobContainerClient $container, array $includes = []): array
    {
        $names = [];

        foreach ($container->getBlobs(options: new GetBlobsOptions(includes: $includes)) as $blob) {
            $names[] = $blob->name;
        }

        return $names;
    }
}
