<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Blob\Integration;

use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Sas\BlobContainerSasPermissions;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use AzureOss\Storage\Blob\Sas\BlobSasPermissions;
use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Sas\AccountSasBuilder;
use AzureOss\Storage\Common\Sas\AccountSasPermissions;
use AzureOss\Storage\Common\Sas\AccountSasResourceTypes;
use AzureOss\Tests\Storage\CreatesTempContainers;
use AzureOss\Tests\Storage\RetryableAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobSasAuthorizationTest extends TestCase
{
    use CreatesTempContainers, RetryableAssertions;

    #[Test]
    public function blob_sas_can_download_a_blob(): void
    {
        $container = $this->tempContainer();
        $blobClient = $container->getBlobClient('blob');
        $blobClient->upload('test');

        $sas = $blobClient->generateSasUri(
            BlobSasBuilder::new()
                ->setPermissions(new BlobSasPermissions(read: true))
                ->setExpiresOn(new \DateTimeImmutable('+1 hour')),
        );

        $sasBlobClient = new BlobClient($sas);

        // Azure can transiently reject signed requests while the SAS becomes available.
        self::assertEventuallySucceeds(
            callback: function () use ($sasBlobClient): void {
                self::assertSame('test', $sasBlobClient->downloadStreaming()->content->getContents());
            },
            maxAttempts: 30,
        );
    }

    #[Test]
    public function container_sas_can_list_blobs(): void
    {
        $container = $this->tempContainer();

        $sas = $container->generateSasUri(
            BlobSasBuilder::new()
                ->setPermissions(new BlobContainerSasPermissions(list: true))
                ->setVersion(ApiVersion::latestGA()->value)
                ->setExpiresOn(new \DateTimeImmutable('+1 hour')),
        );

        $sasContainerClient = new BlobContainerClient($sas);

        // Azure can transiently reject signed requests while the SAS becomes available.
        $blobs = null;
        self::assertEventuallySucceeds(
            callback: function () use ($sasContainerClient, &$blobs): void {
                $blobs = iterator_to_array($sasContainerClient->getBlobs());
            },
            maxAttempts: 30,
        );
        self::assertIsArray($blobs);
    }

    #[Test]
    public function account_sas_can_list_containers(): void
    {
        $sas = $this->service()->generateAccountSasUri(
            AccountSasBuilder::new()
                ->setPermissions(new AccountSasPermissions(list: true))
                ->setResourceTypes(new AccountSasResourceTypes(service: true))
                ->setVersion(ApiVersion::latestGA()->value)
                ->setExpiresOn(new \DateTimeImmutable('+1 hour')),
        );

        $sasServiceClient = new BlobServiceClient($sas);

        $containers = null;
        self::assertEventuallySucceeds(
            callback: function () use ($sasServiceClient, &$containers): void {
                $containers = iterator_to_array($sasServiceClient->getBlobContainers());
            },
            maxAttempts: 30,
        );
        self::assertIsArray($containers);
    }
}
