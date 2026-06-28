<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Blob\Feature;

use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\Exceptions\BlobStorageException;
use AzureOss\Storage\Blob\Models\BlobErrorCode;
use AzureOss\Storage\Blob\Models\BlobHttpHeaders;
use AzureOss\Storage\Blob\Models\BlobInclude;
use AzureOss\Storage\Blob\Models\BlobRequestConditions;
use AzureOss\Storage\Blob\Models\CopyStatus;
use AzureOss\Storage\Blob\Models\DownloadBlobOptions;
use AzureOss\Storage\Blob\Models\GetBlobsOptions;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use AzureOss\Storage\Blob\Sas\BlobSasPermissions;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Tests\Storage\CreatesTempContainers;
use AzureOss\Tests\Storage\CreatesTempFiles;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

final class BlobClientTest extends TestCase
{
    use CreatesTempContainers, CreatesTempFiles;

    #[Test]
    public function download_stream_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $content = 'Lorem ipsum dolor sit amet';
        $blob->upload($content, new UploadBlobOptions(
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

        $result = $blob->downloadStreaming();

        self::assertEquals($result->properties->contentLength, strlen($content));
        self::assertEquals('text/plain', $result->properties->contentType);
        self::assertEquals($content, $result->content->getContents());
    }

    #[Test]
    public function download_stream_with_matching_etag_condition_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $content = 'original';
        $blob->upload($content);
        $eTag = $blob->getProperties()->eTag;

        self::assertNotNull($eTag);

        $result = $blob->downloadStreaming(new DownloadBlobOptions(
            conditions: new BlobRequestConditions(ifMatch: $eTag),
        ));

        self::assertSame($content, $result->content->getContents());
    }

    #[Test]
    public function download_stream_with_stale_etag_condition_fails(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $blob->upload('original');
        $staleETag = $blob->getProperties()->eTag;

        self::assertNotNull($staleETag);

        $blob->upload('updated');

        self::assertBlobStorageException(
            BlobErrorCode::ConditionNotMet,
            fn () => $blob->downloadStreaming(new DownloadBlobOptions(
                conditions: new BlobRequestConditions(ifMatch: $staleETag),
            )),
        );
    }

    #[Test]
    public function download_streams_throws_if_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->getBlobClient('noop')->downloadStreaming(),
        );
    }

    #[Test]
    public function download_stream_throws_if_blob_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::BlobNotFound,
            fn () => $this->tempContainer()->getBlobClient('noop')->downloadStreaming(),
        );
    }

    #[Test]
    public function get_properties_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $content = 'Lorem ipsum dolor sit amet';
        $blob->upload($content, new UploadBlobOptions(
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

        $result = $blob->getProperties();

        self::assertEquals($result->contentLength, strlen($content));
        self::assertEquals('text/plain', $result->contentType);
    }

    #[Test]
    public function get_properties_throws_if_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->getBlobClient('noop')->getProperties(),
        );
    }

    #[Test]
    public function get_properties_throws_if_blob_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::BlobNotFound,
            fn () => $this->tempContainer()->getBlobClient('noop')->getProperties(),
        );
    }

    #[Test]
    public function delete_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $blob->upload('test');

        self::assertTrue($blob->exists());

        $blob->delete();

        self::assertFalse($blob->exists());
    }

    #[Test]
    public function delete_throws_if_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->getBlobClient('noop')->delete(),
        );
    }

    #[Test]
    public function delete_throws_if_blob_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::BlobNotFound,
            fn () => $this->tempContainer()->getBlobClient('noop')->delete(),
        );
    }

    #[Test]
    public function delete_if_exists_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $blob->upload('test');

        self::assertTrue($blob->exists());

        $blob->deleteIfExists();

        self::assertFalse($blob->exists());
    }

    #[Test]
    public function delete_if_exists_throws_if_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->getBlobClient('noop')->deleteIfExists(),
        );
    }

    #[Test]
    public function delete_if_exists_doesnt_throws_if_blob_doesnt_exist(): void
    {
        $this->expectNotToPerformAssertions();

        $this->tempContainer()->getBlobClient('noop')->deleteIfExists();
    }

    #[Test]
    public function soft_deleted_blob_can_be_listed_and_restored(): void
    {
        $container = $this->tempContainer(softDeletes: true);
        $blob = $container->getBlobClient('recoverable.txt');
        $blob->upload('recover me');
        $blob->delete();

        $deletedBlobs = iterator_to_array($container->getBlobs(
            prefix: 'recoverable.txt',
            options: new GetBlobsOptions(includes: [BlobInclude::DELETED]),
        ));

        self::assertCount(1, $deletedBlobs);
        self::assertTrue($deletedBlobs[0]->isDeleted);
        self::assertNotNull($deletedBlobs[0]->properties->deletedOn);
        self::assertNotNull($deletedBlobs[0]->properties->remainingRetentionDays);

        $blob->undelete();

        self::assertSame('recover me', $blob->downloadStreaming()->content->getContents());
    }

    #[Test]
    public function exists_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        self::assertFalse($blob->exists());

        $blob->upload('test');

        self::assertTrue($blob->exists());
    }

    #[Test]
    public function exists_works_throws_if_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->getBlobClient('noop')->exists(),
        );
    }

    #[Test]
    public function upload_works_with_single_upload(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');
        $file = $this->tempFile(1000);

        $beforeUploadContent = $file->getContents();
        $file->rewind();

        $blob->upload($file, new UploadBlobOptions(
            initialTransferSize: 2000,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

        $properties = $blob->getProperties();

        self::assertEquals('text/plain', $properties->contentType);
        self::assertEquals(1000, $properties->contentLength);

        $afterUploadContent = $blob->downloadStreaming()->content->getContents();

        self::assertEquals($beforeUploadContent, $afterUploadContent);
    }

    #[Test]
    public function upload_with_matching_etag_condition_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $blob->upload('original');
        $eTag = $blob->getProperties()->eTag;

        self::assertNotNull($eTag);

        $blob->upload('updated', new UploadBlobOptions(
            conditions: new BlobRequestConditions(ifMatch: $eTag),
        ));

        self::assertSame('updated', $blob->downloadStreaming()->content->getContents());
    }

    #[Test]
    public function upload_with_stale_etag_condition_fails(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $blob->upload('original');
        $staleETag = $blob->getProperties()->eTag;

        self::assertNotNull($staleETag);

        $blob->upload('updated');

        try {
            $blob->upload('should-not-write', new UploadBlobOptions(
                conditions: new BlobRequestConditions(ifMatch: $staleETag),
            ));

            self::fail('Expected stale ETag upload to fail.');
        } catch (BlobStorageException $e) {
            self::assertSame(BlobErrorCode::ConditionNotMet, $e->errorCode);
            self::assertSame('updated', $blob->downloadStreaming()->content->getContents());
        }
    }

    #[Test]
    public function upload_to_leased_blob_requires_matching_lease_id(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $blob->upload('original');
        $leaseClient = $blob->getBlobLeaseClient();
        $lease = $leaseClient->acquire(15);

        try {
            try {
                $blob->upload('should-not-write');

                self::fail('Expected upload without lease ID to fail.');
            } catch (BlobStorageException $e) {
                self::assertSame(BlobErrorCode::LeaseIdMissing, $e->errorCode);
                self::assertSame('original', $blob->downloadStreaming()->content->getContents());
            }

            $blob->upload('updated', new UploadBlobOptions(
                conditions: new BlobRequestConditions(leaseId: $lease->leaseId),
            ));

            self::assertSame('updated', $blob->downloadStreaming()->content->getContents());
        } finally {
            $leaseClient->release();
        }
    }

    #[Test]
    public function download_from_leased_blob_with_matching_lease_id_works_and_wrong_lease_id_fails(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $blob->upload('content');
        $leaseClient = $blob->getBlobLeaseClient();
        $lease = $leaseClient->acquire(15);

        try {
            $result = $blob->downloadStreaming(new DownloadBlobOptions(
                conditions: new BlobRequestConditions(leaseId: $lease->leaseId),
            ));

            self::assertSame('content', $result->content->getContents());

            self::assertBlobStorageException(
                BlobErrorCode::LeaseIdMismatchWithBlobOperation,
                fn () => $blob->downloadStreaming(new DownloadBlobOptions(
                    conditions: new BlobRequestConditions(leaseId: '11111111-1111-4111-8111-111111111111'),
                )),
            );
        } finally {
            $leaseClient->release();
        }
    }

    #[Test]
    public function upload_works_with_size_equal_to_initial_transfer_size(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');
        $file = $this->tempFile(1000);

        $beforeUploadContent = $file->getContents();
        $file->rewind();

        $blob->upload($file, new UploadBlobOptions(
            initialTransferSize: 1000,
            maximumTransferSize: 100,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

        $result = $blob->downloadStreaming();

        self::assertEquals($beforeUploadContent, $result->content->getContents());
        self::assertEquals(1000, $result->properties->contentLength);
    }

    #[Test]
    public function upload_works_with_parallel_upload(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');
        $file = $this->tempFile(1000);

        $beforeUploadContent = $file->getContents();
        $file->rewind();

        $blob->upload($file, new UploadBlobOptions(
            initialTransferSize: 500,
            maximumTransferSize: 100,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

        $properties = $blob->getProperties();

        self::assertEquals('text/plain', $properties->contentType);
        self::assertEquals(1000, $properties->contentLength);

        $result = $blob->downloadStreaming();

        self::assertEquals($beforeUploadContent, $result->content->getContents());
        self::assertEquals(md5($beforeUploadContent), $result->properties->contentMD5);
    }

    #[Test]
    public function upload_works_with_unknown_sized_stream(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');
        $file = $this->tempFile(1000);

        $stream = new class($file) implements StreamInterface
        {
            use StreamDecoratorTrait;

            public function detach()
            {
                return null;
            }

            public function getSize(): ?int
            {
                return null;
            }
        };

        $beforeUploadContent = $file->getContents();
        $file->rewind();

        $blob->upload($stream, new UploadBlobOptions(
            initialTransferSize: 500,
            maximumTransferSize: 100,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

        $properties = $blob->getProperties();

        self::assertEquals('text/plain', $properties->contentType);
        self::assertEquals(1000, $properties->contentLength);

        $result = $blob->downloadStreaming();

        self::assertEquals($beforeUploadContent, $result->content->getContents());
        self::assertEquals(md5($beforeUploadContent), $result->properties->contentMD5);
    }

    #[Test]
    public function upload_works_with_non_seekable_stream(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');
        $file = $this->tempFile(1000);

        $stream = new class(new NoSeekStream($file)) implements StreamInterface
        {
            use StreamDecoratorTrait;

            public function detach()
            {
                return null;
            }
        };

        $beforeUploadContent = $file->getContents();
        $file->rewind();

        $blob->upload($stream, new UploadBlobOptions(
            initialTransferSize: 500,
            maximumTransferSize: 100,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

        $properties = $blob->getProperties();

        self::assertEquals('text/plain', $properties->contentType);
        self::assertEquals(1000, $properties->contentLength);

        $result = $blob->downloadStreaming();

        self::assertEquals($beforeUploadContent, $result->content->getContents());
        self::assertEquals(md5($beforeUploadContent), $result->properties->contentMD5);
    }

    #[Test]
    public function upload_works_with_empty_file(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $blob->upload('', new UploadBlobOptions(
            initialTransferSize: 500,
            maximumTransferSize: 100,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

        $properties = $blob->getProperties();

        self::assertEquals('text/plain', $properties->contentType);
        self::assertEquals(0, $properties->contentLength);

        $afterUploadContent = $blob->downloadStreaming()->content->getContents();

        self::assertEquals('', $afterUploadContent);
    }

    #[Test]
    public function upload_throws_if_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->getBlobClient('noop')->upload('test'),
        );
    }

    #[Test]
    #[Group('slow')]
    #[DataProvider('benchFiles')]
    public function upload_uses_low_memory(int $fileSize, int $count): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('benchmark');

        $startMemory = memory_get_peak_usage(true);

        for ($i = 0; $i < $count; $i++) {
            $file = $this->tempFile($fileSize);
            $blob->upload($file);
        }

        $endMemory = memory_get_peak_usage(true);

        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB

        // Assert memory usage is reasonable (< 16MB)
        self::assertLessThan(16, $memoryUsed, 'Memory usage should be less than 16MB');
    }

    public static function benchFiles(): \Generator
    {
        yield '100x10KB' => [10_000, 100];
        yield '10x10MB' => [10_000_000, 10];
        yield '5x100MB' => [100_000_000, 5];
        yield '2x1GB' => [1_000_000_000, 2];
        yield '1x4GB' => [4_000_000_000, 1];
    }

    #[Test]
    public function sync_copy_from_url_works_with_sas(): void
    {
        $sourceContainer = $this->tempContainer();
        $targetContainer = $this->tempContainer();

        $sourceBlobClient = $sourceContainer->getBlobClient('to_copy');
        $sourceBlobClient->upload('This should be copied!');
        $sourceSas = $sourceBlobClient->generateSasUri(
            BlobSasBuilder::new()
                ->setPermissions(new BlobSasPermissions(read: true))
                ->setExpiresOn((new \DateTime)->modify('+ 1min')),
        );

        $targetBlobClient = $targetContainer->getBlobClient('copied');
        $result = $targetBlobClient->syncCopyFromUri($sourceSas);

        self::assertEquals(CopyStatus::SUCCESS, $result->copyStatus);

        $sourceContent = $sourceBlobClient->downloadStreaming()->content->getContents();
        $targetContent = $targetBlobClient->downloadStreaming()->content->getContents();

        self::assertEquals($targetContent, $sourceContent);
    }

    #[Test]
    public function sync_copy_from_url_works_with_public_container(): void
    {
        $sourceContainer = $this->tempContainer(public: true);
        $targetContainer = $this->tempContainer();

        $sourceBlobClient = $sourceContainer->getBlobClient('to_copy');
        $sourceBlobClient->upload('This should be copied from public container!');

        $targetBlobClient = $targetContainer->getBlobClient('copied');
        $result = $targetBlobClient->syncCopyFromUri($sourceBlobClient->uri);

        self::assertEquals(CopyStatus::SUCCESS, $result->copyStatus);

        $sourceContent = $sourceBlobClient->downloadStreaming()->content->getContents();
        $targetContent = $targetBlobClient->downloadStreaming()->content->getContents();

        self::assertEquals($targetContent, $sourceContent);
    }

    #[Test]
    public function sync_copy_from_url_throws_if_source_container_doesnt_exist(): void
    {
        $sourceContainer = $this->service(public: true)->getContainerClient('nonexistent-'.uniqid());
        $sourceBlob = $sourceContainer->getBlobClient('to_copy');

        $targetContainer = $this->tempContainer();
        self::assertBlobStorageException(
            BlobErrorCode::CannotVerifyCopySource,
            fn () => $targetContainer->getBlobClient('test')->syncCopyFromUri($sourceBlob->uri),
        );
    }

    #[Test]
    public function sync_copy_from_url_works_throws_if_source_blob_doesnt_exist(): void
    {
        $sourceContainer = $this->tempContainer(public: true);
        $sourceBlob = $sourceContainer->getBlobClient('to_copy');

        $targetContainer = $this->tempContainer();
        self::assertBlobStorageException(
            BlobErrorCode::CannotVerifyCopySource,
            fn () => $targetContainer->getBlobClient('test')->syncCopyFromUri($sourceBlob->uri),
        );
    }

    #[Test]
    public function start_copy_from_url_works_with_sas(): void
    {
        $sourceContainer = $this->tempContainer();
        $sourceBlobClient = $sourceContainer->getBlobClient('to_copy');
        $sourceBlobClient->upload('This should be copied!');
        $sourceSas = $sourceBlobClient->generateSasUri(
            BlobSasBuilder::new()
                ->setPermissions(new BlobSasPermissions(read: true))
                ->setExpiresOn((new \DateTime)->modify('+ 1min')),
        );

        $targetContainer = $this->tempContainer();
        $targetBlob = $targetContainer->getBlobClient('copied');
        $targetBlob->startCopyFromUri($sourceSas);

        // this might finish sync or async, but we can't check for a specific behaviour

        self::assertTrue($targetBlob->getProperties()->copyStatus !== null);
    }

    #[Test]
    public function start_copy_from_url_works_with_public_container(): void
    {
        $sourceContainer = $this->tempContainer(public: true);
        $sourceBlobClient = $sourceContainer->getBlobClient('to_copy');
        $sourceBlobClient->upload('This should be copied from public container!');

        $targetContainer = $this->tempContainer();
        $targetBlob = $targetContainer->getBlobClient('copied');
        $targetBlob->startCopyFromUri($sourceBlobClient->uri);

        // this might finish sync or async, but we can't check for a specific behaviour

        self::assertTrue($targetBlob->getProperties()->copyStatus !== null);
    }

    #[Test]
    public function start_copy_from_url_throws_if_source_blob_doesnt_exist(): void
    {
        $sourceContainer = $this->tempContainer(public: true);
        $sourceBlobClient = $sourceContainer->getBlobClient('to_copy');

        $targetContainer = $this->tempContainer();
        self::assertBlobStorageException(
            BlobErrorCode::CannotVerifyCopySource,
            fn () => $targetContainer->getBlobClient('test')->startCopyFromUri($sourceBlobClient->uri),
        );
    }

    #[Test]
    public function abort_copy_from_url_works(): void
    {
        // found no reliable way to test this, because the copy operation is too fast
        // this depends on the blob server load

        self::markTestSkipped();
    }

    #[Test]
    public function abort_copy_from_url_throws_if_copy_id_doesnt_exist(): void
    {
        $sourceContainer = $this->tempContainer(public: true);
        $sourceBlobClient = $sourceContainer->getBlobClient('to_copy');
        $sourceBlobClient->upload('This should be copied!');

        $targetContainer = $this->tempContainer();
        $targetBlob = $targetContainer->getBlobClient('copied');
        $result = $targetBlob->syncCopyFromUri($sourceBlobClient->uri);

        self::assertBlobStorageException(
            BlobErrorCode::NoPendingCopyOperation,
            fn () => $targetBlob->abortCopyFromUri($result->copyId),
        );
    }

    #[Test]
    public function wait_for_copy_completion_works_with_sync_copy(): void
    {
        $sourceContainer = $this->tempContainer(public: true);
        $sourceBlobClient = $sourceContainer->getBlobClient('to_copy');
        $sourceBlobClient->upload('This should be copied!');

        $targetContainer = $this->tempContainer();
        $targetBlob = $targetContainer->getBlobClient('copied');
        $targetBlob->syncCopyFromUri($sourceBlobClient->uri);

        $properties = $targetBlob->waitForCopyCompletion();

        self::assertEquals(CopyStatus::SUCCESS, $properties->copyStatus);
    }

    #[Test]
    public function wait_for_copy_completion_works_with_async_copy(): void
    {
        $sourceContainer = $this->tempContainer(public: true);
        $sourceBlob = $sourceContainer->getBlobClient('from');
        // Create a 10MB stream to increase the chance of pending state
        $sourceBlob->upload($this->tempFile(10 * 1024 * 1024));

        $targetContainer = $this->tempContainer();
        $targetBlob = $targetContainer->getBlobClient('to');

        $targetBlob->startCopyFromUri($sourceBlob->uri);

        // Check if copy is still pending, if not skip test as copy was too fast
        $properties = $targetBlob->getProperties();
        if ($properties->copyStatus !== CopyStatus::PENDING) {
            self::markTestSkipped('Copy operation completed too quickly to test timeout');
        }

        $targetBlob->waitForCopyCompletion(pollingIntervalMs: 100);

        $sourceContent = $sourceBlob->downloadStreaming()->content->getContents();
        $targetContent = $targetBlob->downloadStreaming()->content->getContents();

        self::assertEquals($sourceContent, $targetContent);
    }

    #[Test]
    public function wait_for_copy_completion_throws_timeout(): void
    {
        $sourceContainer = $this->tempContainer(public: true);
        $sourceBlob = $sourceContainer->getBlobClient('from');
        // Create a 10MB stream to increase the chance of pending state
        $sourceBlob->upload($this->tempFile(10 * 1024 * 1024));

        $targetContainer = $this->tempContainer();
        $targetBlob = $targetContainer->getBlobClient('to');

        $targetBlob->startCopyFromUri($sourceBlob->uri);

        // Check if copy is still pending, if not skip test as copy was too fast
        $properties = $targetBlob->getProperties();
        if ($properties->copyStatus !== CopyStatus::PENDING) {
            self::markTestSkipped('Copy operation completed too quickly to test timeout');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Timeout waiting for blob copy to complete');

        $targetBlob->waitForCopyCompletion(pollingIntervalMs: 100, timeoutMs: 1);
    }

    #[Test]
    public function wait_for_copy_completion_returns_immediately_when_no_copy_operation(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');
        $blob->upload('test content');

        $properties = $blob->waitForCopyCompletion();

        self::assertNull($properties->copyStatus);
    }

    #[Test]
    public function can_generate_sas_uri_works(): void
    {
        $containerClient = new BlobClient(new Uri('https://testing.blob.core.windows.net/testing/some-blob'));

        self::assertFalse($containerClient->canGenerateSasUri());

        $containerClient = new BlobClient(
            new Uri('https://testing.blob.core.windows.net/testing/some-blob'),
            new StorageSharedKeyCredential('noop', 'noop'),
        );

        self::assertTrue($containerClient->canGenerateSasUri());
    }

    #[Test]
    public function generate_sas_uri_works(): void
    {
        $this->expectNotToPerformAssertions();

        $container = $this->tempContainer();
        $blobClient = $container->getBlobClient('blob');
        $blobClient->upload('test');

        $sas = $blobClient->generateSasUri(
            BlobSasBuilder::new()
                ->setPermissions(new BlobSasPermissions(read: true))
                ->setExpiresOn((new \DateTime)->modify('+ 1min')),
        );

        $sasBlobClient = new BlobClient($sas);

        $sasBlobClient->downloadStreaming();
    }

    #[Test]
    public function set_tags_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');
        $blob->upload('');
        $blob->setTags(['foo' => 'bar', 'baz' => 'boo']);

        $tags = $blob->getTags();

        self::assertEquals($tags['foo'], 'bar');
        self::assertEquals($tags['baz'], 'boo');
    }

    #[Test]
    public function set_tags_throws_when_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->getBlobClient('noop')->setTags(['foo' => 'bar']),
        );
    }

    #[Test]
    public function set_tags_throws_if_blob_doesnt_exist(): void
    {
        $container = $this->tempContainer();

        self::assertBlobStorageException(
            BlobErrorCode::BlobNotFound,
            fn () => $container->getBlobClient('test')->setTags(['foo' => 'bar']),
        );
    }

    #[Test]
    public function set_tags_throws_when_tag_key_is_too_large(): void
    {
        $container = $this->tempContainer();

        self::assertBlobStorageException(
            BlobErrorCode::TagsTooLarge,
            fn () => $container->getBlobClient('test')->setTags([str_pad('', 1000, 'a') => 'noop']),
        );
    }

    #[Test]
    public function set_tags_throws_when_tag_value_is_too_large(): void
    {
        $container = $this->tempContainer();

        self::assertBlobStorageException(
            BlobErrorCode::TagsTooLarge,
            fn () => $container->getBlobClient('test')->setTags(['noop' => str_pad('', 1000, 'a')]),
        );
    }

    #[Test]
    public function set_tags_throws_when_too_many_tags_are_provided(): void
    {
        $tags = [];

        for ($i = 0; $i < 1000; $i++) {
            $tags["tag-$i"] = 'noop';
        }

        $container = $this->tempContainer();

        self::assertBlobStorageException(
            BlobErrorCode::TagsTooLarge,
            fn () => $container->getBlobClient('test')->setTags($tags),
        );
    }

    #[Test]
    public function get_tags_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');
        $blob->upload('');
        $blob->setTags(['foo' => 'bar', 'baz' => 'boo']);

        $tags = $blob->getTags();

        self::assertEquals($tags['foo'], 'bar');
        self::assertEquals($tags['baz'], 'boo');
    }

    #[Test]
    public function get_tags_throws_when_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->getBlobClient('noop')->getTags(),
        );
    }

    #[Test]
    public function get_tags_throws_if_blob_doesnt_exist(): void
    {
        $container = $this->tempContainer();

        self::assertBlobStorageException(
            BlobErrorCode::BlobNotFound,
            fn () => $container->getBlobClient('test')->getTags(),
        );
    }

    #[Test]
    public function set_metadata_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');
        $blob->upload('');
        $props = $blob->getProperties();

        self::assertEmpty($props->metadata);

        $blob->setMetadata(['foo' => 'bar', 'baz' => 'qaz']);

        $props = $blob->getProperties();

        self::assertNotNull($props->metadata);
        self::assertEquals('bar', $props->metadata['foo']);
        self::assertEquals('qaz', $props->metadata['baz']);
    }

    #[Test]
    public function set_metadata_throws_when_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->getBlobClient('noop')->setMetadata(['foo' => 'bar']),
        );
    }

    #[Test]
    public function set_metadata_throws_if_blob_doesnt_exist(): void
    {
        $container = $this->tempContainer();

        self::assertBlobStorageException(
            BlobErrorCode::BlobNotFound,
            fn () => $container->getBlobClient('noop')->setMetadata(['foo' => 'bar']),
        );
    }

    #[Test]
    public function getting_and_setting_http_headers_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        $originalContent = 'Hello, World!';
        $compressedContent = gzcompress($originalContent);
        if ($compressedContent === false) {
            self::fail('Failed to compress content');
        }

        $blob->upload(
            $compressedContent,
            new UploadBlobOptions(httpHeaders: new BlobHttpHeaders(
                cacheControl: 'immutable',
                contentDisposition: 'inline',
                contentEncoding: 'gzip',
                contentHash: md5($compressedContent, true),
                contentLanguage: 'en',
                contentType: 'text/plain',
            )),
        );

        $properties = $blob->getProperties();

        self::assertEquals('text/plain', $properties->contentType);
        self::assertEquals('immutable', $properties->cacheControl);
        self::assertEquals('inline', $properties->contentDisposition);
        self::assertEquals('en', $properties->contentLanguage);
        self::assertEquals('gzip', $properties->contentEncoding);

        // The content is automatically decompressed when downloaded
        self::assertEquals($originalContent, $blob->downloadStreaming()->content->getContents());
    }

    #[Test]
    public function set_http_headers_works(): void
    {
        $container = $this->tempContainer();
        $blob = $container->getBlobClient('test');

        // Upload with initial content type
        $blob->upload('test content', new UploadBlobOptions(httpHeaders: new BlobHttpHeaders(
            contentType: 'application/octet-stream',
        )));

        $initialProps = $blob->getProperties();
        self::assertEquals('application/octet-stream', $initialProps->contentType);

        // Change content type using setHttpHeaders
        $blob->setHttpHeaders(new BlobHttpHeaders(
            cacheControl: 'public, max-age=3600',
            contentType: 'text/plain',
        ));

        $updatedProps = $blob->getProperties();
        self::assertEquals('text/plain', $updatedProps->contentType);
        self::assertEquals('public, max-age=3600', $updatedProps->cacheControl);
    }

    #[Test]
    public function set_http_headers_after_copy_works(): void
    {
        $container = $this->tempContainer(public: true);

        // Create source blob
        $sourceBlobClient = $container->getBlobClient('source.txt');
        $sourceBlobClient->upload('content to copy', new UploadBlobOptions(httpHeaders: new BlobHttpHeaders(
            contentType: 'application/octet-stream',
        )));

        // Copy to target
        $targetBlobClient = $container->getBlobClient('target.txt');
        $copyResult = $targetBlobClient->syncCopyFromUri($sourceBlobClient->uri);

        self::assertEquals(CopyStatus::SUCCESS, $copyResult->copyStatus);

        // Verify copied content type
        $copiedProps = $targetBlobClient->getProperties();
        self::assertEquals('application/octet-stream', $copiedProps->contentType);

        // Update properties on copied blob
        $targetBlobClient->setHttpHeaders(new BlobHttpHeaders(
            contentType: 'image/jpeg',
        ));

        $updatedProps = $targetBlobClient->getProperties();
        self::assertEquals('image/jpeg', $updatedProps->contentType);
    }

    #[Test]
    public function set_http_headers_throws_if_blob_doesnt_exist(): void
    {
        $container = $this->tempContainer();

        self::assertBlobStorageException(
            BlobErrorCode::BlobNotFound,
            fn () => $container->getBlobClient('test')->setHttpHeaders(new BlobHttpHeaders(
                contentType: 'text/plain',
            )),
        );
    }

    #[Test]
    public function set_http_headers_throws_if_container_doesnt_exist(): void
    {
        self::assertBlobStorageException(
            BlobErrorCode::ContainerNotFound,
            fn () => $this->service()->getContainerClient('noop')->getBlobClient('noop')->setHttpHeaders(
                new BlobHttpHeaders(contentType: 'text/plain'),
            ),
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
