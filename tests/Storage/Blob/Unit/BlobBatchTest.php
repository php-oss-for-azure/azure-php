<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Blob\Unit;

use AzureOss\Identity\AccessToken;
use AzureOss\Identity\CredentialUnavailableException;
use AzureOss\Identity\TokenCredential;
use AzureOss\Identity\TokenRequestContext;
use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Specialized\BlobBatchClient;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobBatchTest extends TestCase
{
    #[Test]
    public function count_returns_number_of_operations(): void
    {
        $batch = self::batchClient()->createBatch();

        self::assertCount(0, $batch);

        $batch->deleteBlob('a.txt');
        $batch->deleteBlob('b.txt');

        self::assertCount(2, $batch);
    }

    #[Test]
    public function submit_batch_rejects_empty_batch(): void
    {
        $client = self::batchClient();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot submit an empty batch.');

        $client->submitBatch($client->createBatch());
    }

    #[Test]
    public function delete_blob_rejects_more_than_256_operations(): void
    {
        $batch = self::batchClient()->createBatch();

        foreach (range(1, BlobBatchClient::MAX_BATCH_SIZE) as $i) {
            $batch->deleteBlob("blob-{$i}");
        }

        try {
            $batch->deleteBlob('one-too-many');

            self::fail('Expected adding a 257th operation to fail.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('A batch can contain at most 256 operations.', $e->getMessage());
        }

        self::assertCount(256, $batch);
    }

    #[Test]
    public function delete_blob_rejects_blob_client_from_another_container(): void
    {
        $service = new BlobServiceClient(new Uri('https://account.example.invalid'));
        $batch = $service->getContainerClient('container')->getBlobBatchClient()->createBatch();

        try {
            $batch->deleteBlob($service->getContainerClient('other')->getBlobClient('a.txt'));

            self::fail('Expected adding a blob from another container to fail.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Blob "a.txt" is not in container "container".', $e->getMessage());
        }

        self::assertCount(0, $batch);
    }

    #[Test]
    public function delete_blob_rejects_blob_client_on_another_endpoint(): void
    {
        $batch = self::batchClient()->createBatch();

        try {
            $batch->deleteBlob(new BlobClient(new Uri('https://account.secondary.example.invalid/container/a.txt')));

            self::fail('Expected adding a blob on another endpoint to fail.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Blob client for "a.txt" points at a different endpoint than the batch client.', $e->getMessage());
        }

        self::assertCount(0, $batch);
    }

    #[Test]
    public function delete_blob_rejects_empty_name(): void
    {
        $batch = self::batchClient()->createBatch();

        foreach (['', '/', '//'] as $name) {
            try {
                $batch->deleteBlob($name);

                self::fail("Expected adding \"{$name}\" to fail.");
            } catch (\InvalidArgumentException $e) {
                self::assertSame('Blob name cannot be empty.', $e->getMessage());
            }
        }

        self::assertCount(0, $batch);
    }

    #[Test]
    public function account_batch_client_requires_container_in_name(): void
    {
        $batch = (new BlobServiceClient(new Uri('https://account.example.invalid')))->getBlobBatchClient()->createBatch();

        foreach (['a.txt', '/a.txt', 'container/'] as $name) {
            try {
                $batch->deleteBlob($name);

                self::fail("Expected adding \"{$name}\" to fail.");
            } catch (\InvalidArgumentException $e) {
                self::assertSame("Blob name \"{$name}\" must be \"<container>/<blob>\" for a storage account batch client.", $e->getMessage());
            }
        }

        $batch->deleteBlob('container/a.txt');

        self::assertCount(1, $batch);
    }

    #[Test]
    public function service_client_creates_account_batch_client(): void
    {
        $service = new BlobServiceClient(new Uri('http://127.0.0.1:10000/myaccount'));

        self::assertNull($service->getBlobBatchClient()->containerName);
    }

    #[Test]
    public function delete_blobs_async_rejects_when_token_acquisition_fails(): void
    {
        $credential = new class implements TokenCredential
        {
            public function getToken(TokenRequestContext $context): AccessToken
            {
                throw new CredentialUnavailableException('No credential.');
            }
        };
        $client = new BlobBatchClient(self::uri(), $credential, 'container');

        $promise = $client->deleteBlobsAsync(['a.txt']);

        $this->expectException(CredentialUnavailableException::class);
        $this->expectExceptionMessage('No credential.');

        $promise->wait();
    }

    private static function batchClient(): BlobBatchClient
    {
        return new BlobBatchClient(self::uri(), containerName: 'container');
    }

    private static function uri(): Uri
    {
        return new Uri('https://account.example.invalid/container');
    }
}
