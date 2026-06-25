<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Feature;

use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Models\BlobHttpHeaders;
use AzureOss\Storage\Blob\Models\BlobRequestConditions;
use AzureOss\Storage\Blob\Models\DeleteBlobOptions;
use AzureOss\Storage\Blob\Models\DeleteContainerOptions;
use AzureOss\Storage\Blob\Models\DownloadBlobOptions;
use AzureOss\Storage\Blob\Models\GetBlobPropertiesOptions;
use AzureOss\Storage\Blob\Models\SetBlobHttpHeadersOptions;
use AzureOss\Storage\Blob\Models\SetBlobMetadataOptions;
use AzureOss\Storage\Blob\Models\SetContainerMetadataOptions;
use AzureOss\Storage\Blob\Models\SyncCopyFromUriOptions;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Common\Models\ETag;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Server\Server;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConditionalRequestHeadersTest extends TestCase
{
    private BlobClient $blob;

    private BlobContainerClient $container;

    public static function setUpBeforeClass(): void
    {
        Server::start();
    }

    public static function tearDownAfterClass(): void
    {
        Server::stop();
    }

    protected function setUp(): void
    {
        Server::flush();

        /** @phpstan-ignore-next-line */
        $service = new BlobServiceClient(new Uri(Server::$url.'/devstoreaccount1'));
        $this->container = $service->getContainerClient('test');
        $this->blob = $this->container->getBlobClient('test');
    }

    #[Test]
    public function upload_single_sends_request_conditions(): void
    {
        Server::enqueue([new Response(200)]);

        $this->blob->upload('test', new UploadBlobOptions(
            conditions: new BlobRequestConditions(ifNoneMatch: ETag::all()),
        ));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('*', $requests[0]->getHeaderLine('If-None-Match'));
    }

    #[Test]
    public function download_streaming_sends_request_conditions(): void
    {
        Server::enqueue([new Response(200, [
            'Last-Modified' => 'Wed, 01 Jan 2025 12:34:56 GMT',
            'Content-Length' => '4',
            'Content-Type' => 'text/plain',
        ], 'test')]);

        $this->blob->downloadStreaming(new DownloadBlobOptions(
            conditions: new BlobRequestConditions(ifMatch: new ETag('"blob-etag"')),
        ));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('"blob-etag"', $requests[0]->getHeaderLine('If-Match'));
    }

    #[Test]
    public function get_blob_properties_sends_request_conditions(): void
    {
        Server::enqueue([new Response(200, [
            'Last-Modified' => 'Wed, 01 Jan 2025 12:34:56 GMT',
            'Content-Length' => '4',
            'Content-Type' => 'text/plain',
        ])]);

        $this->blob->getProperties(new GetBlobPropertiesOptions(
            conditions: new BlobRequestConditions(ifNoneMatch: new ETag('"blob-etag"')),
        ));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('"blob-etag"', $requests[0]->getHeaderLine('If-None-Match'));
    }

    #[Test]
    public function set_blob_metadata_sends_request_conditions(): void
    {
        Server::enqueue([new Response(200)]);

        $this->blob->setMetadata([], new SetBlobMetadataOptions(
            conditions: new BlobRequestConditions(ifMatch: new ETag('"blob-etag"')),
        ));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('"blob-etag"', $requests[0]->getHeaderLine('If-Match'));
    }

    #[Test]
    public function set_blob_http_headers_sends_request_conditions(): void
    {
        Server::enqueue([new Response(200)]);

        $this->blob->setHttpHeaders(new BlobHttpHeaders(contentType: 'text/plain'), new SetBlobHttpHeadersOptions(
            conditions: new BlobRequestConditions(ifMatch: new ETag('"blob-etag"')),
        ));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('"blob-etag"', $requests[0]->getHeaderLine('If-Match'));
    }

    #[Test]
    public function upload_block_list_commit_sends_request_conditions(): void
    {
        Server::enqueue([
            new Response(200),
            new Response(201),
        ]);

        $this->blob->upload('test', new UploadBlobOptions(
            initialTransferSize: 0,
            maximumTransferSize: 8_000_000,
            conditions: new BlobRequestConditions(
                ifMatch: new ETag('"blob-etag"'),
                leaseId: '11111111-1111-4111-8111-111111111111',
            ),
        ));

        $requests = Server::received();

        self::assertCount(2, $requests);
        parse_str($requests[1]->getUri()->getQuery(), $query);
        parse_str($requests[0]->getUri()->getQuery(), $stageBlockQuery);
        self::assertSame('block', $stageBlockQuery['comp'] ?? null);
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[0]->getHeaderLine('x-ms-lease-id'));
        self::assertSame('blocklist', $query['comp'] ?? null);
        self::assertSame('"blob-etag"', $requests[1]->getHeaderLine('If-Match'));
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[1]->getHeaderLine('x-ms-lease-id'));
    }

    #[Test]
    public function sync_copy_sends_destination_and_source_conditions(): void
    {
        Server::enqueue([new Response(202, [
            'x-ms-copy-id' => 'copy-id',
            'x-ms-copy-status' => 'success',
        ])]);

        $this->blob->syncCopyFromUri(new Uri('https://example.test/source'), new SyncCopyFromUriOptions(
            destinationConditions: new BlobRequestConditions(ifMatch: new ETag('"destination-etag"')),
            sourceConditions: new BlobRequestConditions(
                ifNoneMatch: new ETag('"source-etag"'),
                leaseId: '22222222-2222-4222-8222-222222222222',
            ),
        ));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('"destination-etag"', $requests[0]->getHeaderLine('If-Match'));
        self::assertSame('"source-etag"', $requests[0]->getHeaderLine('x-ms-source-if-none-match'));
        self::assertSame('22222222-2222-4222-8222-222222222222', $requests[0]->getHeaderLine('x-ms-source-lease-id'));
    }

    #[Test]
    public function delete_blob_sends_request_conditions(): void
    {
        Server::enqueue([new Response(202)]);

        $this->blob->delete(new DeleteBlobOptions(
            conditions: new BlobRequestConditions(ifMatch: new ETag('"blob-etag"')),
        ));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('"blob-etag"', $requests[0]->getHeaderLine('If-Match'));
    }

    #[Test]
    public function delete_container_sends_request_conditions(): void
    {
        Server::enqueue([new Response(202)]);

        $this->container->delete(new DeleteContainerOptions(
            conditions: new BlobRequestConditions(ifUnmodifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC')),
        ));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('Wed, 01 Jan 2025 12:34:56 GMT', $requests[0]->getHeaderLine('If-Unmodified-Since'));
    }

    #[Test]
    public function set_container_metadata_sends_request_conditions(): void
    {
        Server::enqueue([new Response(200)]);

        $this->container->setMetadata([], new SetContainerMetadataOptions(
            conditions: new BlobRequestConditions(ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC')),
        ));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('Wed, 01 Jan 2025 12:34:56 GMT', $requests[0]->getHeaderLine('If-Modified-Since'));
    }
}
