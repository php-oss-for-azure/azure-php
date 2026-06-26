<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Feature;

use AzureOss\Storage\Blob\Exceptions\BlobStorageException;
use AzureOss\Storage\Blob\Models\BlobErrorCode;
use AzureOss\Storage\Tests\CreatesTempContainers;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobLeaseClientTest extends TestCase
{
    use CreatesTempContainers;

    #[Test]
    public function blob_lease_client_acquires_renews_changes_and_releases_lease(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $blob->upload('content');

        $leaseClient = $blob->getBlobLeaseClient();
        $lease = $leaseClient->acquire(15);

        self::assertNotNull($lease->leaseId);

        $renewedLease = $leaseClient->renew();

        self::assertSame($lease->leaseId, $renewedLease->leaseId);

        $changedLease = $leaseClient->change('11111111-1111-4111-8111-111111111111');

        self::assertSame('11111111-1111-4111-8111-111111111111', $changedLease->leaseId);

        $leaseClient->release();

        $blob->upload('updated');

        self::assertSame('updated', $blob->downloadStreaming()->content->getContents());
    }

    #[Test]
    public function blob_lease_client_breaks_lease(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $blob->upload('content');

        $leaseClient = $blob->getBlobLeaseClient();
        $leaseClient->acquire(15);
        $leaseClient->break(0);

        $blob->upload('updated');

        self::assertSame('updated', $blob->downloadStreaming()->content->getContents());
    }

    #[Test]
    public function container_lease_client_acquires_and_releases_lease(): void
    {
        $container = $this->tempContainer();

        $leaseClient = $container->getBlobLeaseClient();
        $lease = $leaseClient->acquire(15);

        self::assertNotNull($lease->leaseId);

        try {
            $container->delete();

            self::fail('Expected deleting a leased container without lease ID to fail.');
        } catch (BlobStorageException $e) {
            self::assertSame(BlobErrorCode::LeaseIdMissing, $e->errorCode);
            self::assertTrue($container->exists());
        }

        $leaseClient->release();
        $container->delete();

        self::assertFalse($container->exists());
    }
}
