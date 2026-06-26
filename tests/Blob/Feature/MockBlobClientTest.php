<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Feature;

use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Models\AbortCopyFromUriOptions;
use AzureOss\Storage\Blob\Models\AcquireBlobLeaseOptions;
use AzureOss\Storage\Blob\Models\BlobHttpHeaders;
use AzureOss\Storage\Blob\Models\BlobRequestConditions;
use AzureOss\Storage\Blob\Models\DeleteContainerOptions;
use AzureOss\Storage\Blob\Models\GetBlobTagsOptions;
use AzureOss\Storage\Blob\Models\GetContainerPropertiesOptions;
use AzureOss\Storage\Blob\Models\SetBlobTagsOptions;
use AzureOss\Storage\Blob\Models\SetContainerMetadataOptions;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Common\Models\ETag;
use AzureOss\Storage\Tests\CreatesTempFiles;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Server\Server;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class MockBlobClientTest extends TestCase
{
    use CreatesTempFiles;

    private BlobClient $blob;

    protected function setUp(): void
    {
        Server::start();

        /** @phpstan-ignore-next-line */
        $uri = new Uri(Server::$url.'/devstoreaccount1');
        $service = new BlobServiceClient($uri);
        $container = $service->getContainerClient('test');
        $this->blob = $container->getBlobClient('test');
    }

    protected function tearDown(): void
    {
        Server::stop();
    }

    #[Test]
    public function upload_single_sends_correct_amount_of_requests(): void
    {
        Server::enqueue([
            new Response(200), // only one request
            new Response(501), // fail if more requests
        ]);

        $file = $this->tempFile(1000);
        $this->blob->upload($file, new UploadBlobOptions('text/plain', initialTransferSize: 2000));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('', $requests[0]->getUri()->getQuery());
        self::assertSame('BlockBlob', $requests[0]->getHeaderLine('x-ms-blob-type'));
    }

    #[Test]
    public function upload_parallel_blocks_sends_correct_amount_of_requests(): void
    {
        Server::enqueue([
            ...array_fill(0, 11, new Response(200)), // 10 chunks + 1 commit request
            new Response(501), // fail if more requests
        ]);

        $file = $this->tempFile(50_000_000);
        $this->blob->upload($file, new UploadBlobOptions('text/plain', initialTransferSize: 0, maximumTransferSize: 5_000_000));

        $requests = Server::received();

        self::assertCount(11, $requests);
        foreach (array_slice($requests, 0, 10) as $request) {
            parse_str($request->getUri()->getQuery(), $query);
            self::assertSame('block', $query['comp'] ?? null);
            self::assertArrayHasKey('blockid', $query);
        }

        parse_str($requests[10]->getUri()->getQuery(), $query);
        self::assertSame('blocklist', $query['comp'] ?? null);
    }

    #[Test]
    public function upload_parallel_blocks_sends_only_lease_id_to_stage_block_and_all_conditions_to_commit(): void
    {
        Server::enqueue([
            new Response(200),
            new Response(200),
            new Response(501),
        ]);

        $this->blob->upload('test', new UploadBlobOptions(
            'text/plain',
            initialTransferSize: 0,
            maximumTransferSize: 8_000_000,
            conditions: new BlobRequestConditions(
                ifMatch: new ETag('"match"'),
                ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC'),
                leaseId: '11111111-1111-4111-8111-111111111111',
            ),
        ));

        $requests = Server::received();

        self::assertCount(2, $requests);
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[0]->getHeaderLine('x-ms-lease-id'));
        self::assertSame('', $requests[0]->getHeaderLine('If-Match'));
        self::assertSame('', $requests[0]->getHeaderLine('If-Modified-Since'));
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[1]->getHeaderLine('x-ms-lease-id'));
        self::assertSame('"match"', $requests[1]->getHeaderLine('If-Match'));
        self::assertSame('Wed, 01 Jan 2025 12:34:56 GMT', $requests[1]->getHeaderLine('If-Modified-Since'));
    }

    #[Test]
    public function upload_parallel_blocks_sends_correct_amount_of_requests_for_small_files(): void
    {
        Server::enqueue([
            ...array_fill(0, 2, new Response(200)), // 1 chunks + 1 commit request
            new Response(501), // fail if more requests
        ]);

        $file = $this->tempFile(50_000);
        $this->blob->upload($file, new UploadBlobOptions('text/plain', initialTransferSize: 0, maximumTransferSize: 8_000_000));

        $requests = Server::received();

        self::assertCount(2, $requests);
        parse_str($requests[0]->getUri()->getQuery(), $firstQuery);
        parse_str($requests[1]->getUri()->getQuery(), $secondQuery);
        self::assertSame('block', $firstQuery['comp'] ?? null);
        self::assertSame('blocklist', $secondQuery['comp'] ?? null);
    }

    #[Test]
    public function upload_unknown_sized_stream_uses_block_upload_requests(): void
    {
        Server::enqueue([
            ...array_fill(0, 11, new Response(200)), // 10 chunks + 1 commit request
            new Response(501), // fail if more requests
        ]);

        $file = $this->tempFile(50_000_000);

        $stream = new class($file) implements StreamInterface
        {
            use StreamDecoratorTrait;

            public function getSize(): ?int
            {
                return null;
            }
        };

        $this->blob->upload($stream, new UploadBlobOptions('text/plain', initialTransferSize: 0, maximumTransferSize: 5_000_000));

        $requests = Server::received();

        self::assertCount(11, $requests);
        foreach (array_slice($requests, 0, 10) as $request) {
            parse_str($request->getUri()->getQuery(), $query);
            self::assertSame('block', $query['comp'] ?? null);
        }

        parse_str($requests[10]->getUri()->getQuery(), $query);
        self::assertSame('blocklist', $query['comp'] ?? null);
    }

    #[Test]
    public function upload_unknown_sized_stream_uses_default_block_size_when_not_provided(): void
    {
        Server::enqueue([
            ...array_fill(0, 8, new Response(200)), // 7 chunks + 1 commit request
            new Response(501),
        ]);

        $file = $this->tempFile(50_000_000);

        $stream = new class($file) implements StreamInterface
        {
            use StreamDecoratorTrait;

            public function getSize(): ?int
            {
                return null;
            }
        };

        $this->blob->upload($stream, new UploadBlobOptions('text/plain', initialTransferSize: 0, maximumTransferSize: null));

        $requests = Server::received();

        self::assertCount(8, $requests);
        self::assertSame('8000000', $requests[0]->getHeaderLine('Content-Length'));
        parse_str($requests[7]->getUri()->getQuery(), $query);
        self::assertSame('blocklist', $query['comp'] ?? null);
    }

    #[Test]
    public function upload_uses_single_upload_when_size_equals_initial_transfer_size(): void
    {
        Server::enqueue([
            new Response(200),
            new Response(501),
        ]);

        $file = $this->tempFile(1000);
        $this->blob->upload($file, new UploadBlobOptions('text/plain', initialTransferSize: 1000));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('', $requests[0]->getUri()->getQuery());
    }

    #[Test]
    public function upload_parallel_blocks_sends_correct_amount_of_requests_with_a_network_request(): void
    {
        Server::enqueue([
            new Response(200, body: str_repeat('X', 50_000_000)), // stream for fopen
            ...array_fill(0, 20, new Response(200)), // with network streams some chunks in the beginning are smaller. It should be less than 20 requests still.
            new Response(501), // fail if more requests
        ]);

        /** @phpstan-ignore-next-line */
        $stream = fopen(Server::$url, 'r');

        if ($stream === false) {
            self::fail();
        }

        $this->blob->upload($stream, new UploadBlobOptions('text/plain', initialTransferSize: 0, maximumTransferSize: 5_000_000));

        $requests = Server::received();
        $lastRequestIndex = count($requests) - 1;

        self::assertGreaterThan(1, count($requests));
        parse_str($requests[$lastRequestIndex]->getUri()->getQuery(), $query);
        self::assertSame('blocklist', $query['comp'] ?? null);
    }

    #[Test]
    public function upload_block_upload_preserves_explicit_content_hash_header(): void
    {
        Server::enqueue([
            new Response(200),
            new Response(200),
            new Response(501),
        ]);

        $file = $this->tempFile(1000);
        $contentHash = hash('md5', $file->getContents(), binary: true);
        $file->rewind();

        $this->blob->upload($file, new UploadBlobOptions(
            'text/plain',
            initialTransferSize: 0,
            maximumTransferSize: 8_000_000,
            httpHeaders: new BlobHttpHeaders(contentHash: $contentHash),
        ));

        $requests = Server::received();

        self::assertCount(2, $requests);
        self::assertSame(base64_encode($contentHash), $requests[1]->getHeaderLine('x-ms-blob-content-md5'));
    }

    #[Test]
    public function upload_known_size_stream_automatically_calculates_block_size_when_not_provided(): void
    {
        Server::enqueue([
            new Response(200),
            new Response(200),
            new Response(501),
        ]);

        $stream = new class implements StreamInterface
        {
            private int $remaining = 8_000_001;

            public function __toString(): string
            {
                return '';
            }

            public function close(): void {}

            public function detach()
            {
                return null;
            }

            public function getSize(): int
            {
                return 400_000_050_000;
            }

            public function tell(): int
            {
                return 8_000_001 - $this->remaining;
            }

            public function eof(): bool
            {
                return $this->remaining === 0;
            }

            public function isSeekable(): bool
            {
                return false;
            }

            public function seek($offset, $whence = SEEK_SET): void
            {
                throw new \RuntimeException('Not seekable.');
            }

            public function rewind(): void
            {
                throw new \RuntimeException('Not seekable.');
            }

            public function isWritable(): bool
            {
                return false;
            }

            public function write($string): int
            {
                throw new \RuntimeException('Not writable.');
            }

            public function isReadable(): bool
            {
                return true;
            }

            public function read($length): string
            {
                if ($this->remaining === 0) {
                    return '';
                }

                $chunkLength = min($length, $this->remaining);
                $this->remaining -= $chunkLength;

                return str_repeat('a', $chunkLength);
            }

            public function getContents(): string
            {
                return $this->read($this->remaining);
            }

            public function getMetadata($key = null): mixed
            {
                return $key === null ? [] : null;
            }
        };

        $this->blob->upload($stream, new UploadBlobOptions('text/plain', initialTransferSize: 0, maximumTransferSize: null));

        $requests = Server::received();

        self::assertCount(2, $requests);
        self::assertSame('8000001', $requests[0]->getHeaderLine('Content-Length'));
        parse_str($requests[1]->getUri()->getQuery(), $query);
        self::assertSame('blocklist', $query['comp'] ?? null);
    }

    #[Test]
    public function container_get_properties_sends_only_lease_id_condition(): void
    {
        Server::enqueue([
            new Response(200, [
                'Last-Modified' => 'Wed, 01 Jan 2025 12:34:56 GMT',
                'ETag' => '"container-etag"',
            ]),
            new Response(501),
        ]);

        $serverUrl = Server::$url;
        self::assertIsString($serverUrl);

        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));
        $container = $service->getContainerClient('test');

        $container->getProperties(new GetContainerPropertiesOptions(
            conditions: new BlobRequestConditions(
                ifMatch: new ETag('"match"'),
                leaseId: '11111111-1111-4111-8111-111111111111',
            ),
        ));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[0]->getHeaderLine('x-ms-lease-id'));
        self::assertSame('', $requests[0]->getHeaderLine('If-Match'));
    }

    #[Test]
    public function container_delete_sends_only_supported_conditions(): void
    {
        Server::enqueue([
            new Response(202),
            new Response(501),
        ]);

        $serverUrl = Server::$url;
        self::assertIsString($serverUrl);

        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));
        $container = $service->getContainerClient('test');

        $container->delete(new DeleteContainerOptions(new BlobRequestConditions(
            ifMatch: new ETag('"match"'),
            ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC'),
            ifNoneMatch: ETag::all(),
            ifUnmodifiedSince: new \DateTimeImmutable('2025-01-02 12:34:56 UTC'),
            leaseId: '11111111-1111-4111-8111-111111111111',
        )));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('Wed, 01 Jan 2025 12:34:56 GMT', $requests[0]->getHeaderLine('If-Modified-Since'));
        self::assertSame('Thu, 02 Jan 2025 12:34:56 GMT', $requests[0]->getHeaderLine('If-Unmodified-Since'));
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[0]->getHeaderLine('x-ms-lease-id'));
        self::assertSame('', $requests[0]->getHeaderLine('If-Match'));
        self::assertSame('', $requests[0]->getHeaderLine('If-None-Match'));
    }

    #[Test]
    public function container_set_metadata_sends_only_supported_conditions(): void
    {
        Server::enqueue([
            new Response(200),
            new Response(501),
        ]);

        $serverUrl = Server::$url;
        self::assertIsString($serverUrl);

        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));
        $container = $service->getContainerClient('test');

        $container->setMetadata(['foo' => 'bar'], new SetContainerMetadataOptions(new BlobRequestConditions(
            ifMatch: new ETag('"match"'),
            ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC'),
            ifNoneMatch: ETag::all(),
            ifUnmodifiedSince: new \DateTimeImmutable('2025-01-02 12:34:56 UTC'),
            leaseId: '11111111-1111-4111-8111-111111111111',
        )));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('Wed, 01 Jan 2025 12:34:56 GMT', $requests[0]->getHeaderLine('If-Modified-Since'));
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[0]->getHeaderLine('x-ms-lease-id'));
        self::assertSame('', $requests[0]->getHeaderLine('If-Match'));
        self::assertSame('', $requests[0]->getHeaderLine('If-None-Match'));
        self::assertSame('', $requests[0]->getHeaderLine('If-Unmodified-Since'));
    }

    #[Test]
    public function container_lease_operations_send_only_supported_conditions(): void
    {
        Server::enqueue([
            new Response(201, ['x-ms-lease-id' => '22222222-2222-4222-8222-222222222222']),
            new Response(501),
        ]);

        $serverUrl = Server::$url;
        self::assertIsString($serverUrl);

        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));
        $container = $service->getContainerClient('test');

        $container->getBlobLeaseClient()->acquire(15, new AcquireBlobLeaseOptions(new BlobRequestConditions(
            ifMatch: new ETag('"match"'),
            ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC'),
            ifNoneMatch: ETag::all(),
            ifUnmodifiedSince: new \DateTimeImmutable('2025-01-02 12:34:56 UTC'),
            leaseId: '11111111-1111-4111-8111-111111111111',
        )));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('Wed, 01 Jan 2025 12:34:56 GMT', $requests[0]->getHeaderLine('If-Modified-Since'));
        self::assertSame('Thu, 02 Jan 2025 12:34:56 GMT', $requests[0]->getHeaderLine('If-Unmodified-Since'));
        self::assertSame('', $requests[0]->getHeaderLine('If-Match'));
        self::assertSame('', $requests[0]->getHeaderLine('If-None-Match'));
        self::assertSame('', $requests[0]->getHeaderLine('x-ms-lease-id'));
    }

    #[Test]
    public function abort_copy_from_uri_sends_only_lease_id_condition(): void
    {
        Server::enqueue([
            new Response(204),
            new Response(501),
        ]);

        $this->blob->abortCopyFromUri('copy-id', new AbortCopyFromUriOptions(new BlobRequestConditions(
            ifMatch: new ETag('"match"'),
            ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC'),
            ifNoneMatch: ETag::all(),
            ifUnmodifiedSince: new \DateTimeImmutable('2025-01-02 12:34:56 UTC'),
            leaseId: '11111111-1111-4111-8111-111111111111',
        )));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('abort', $requests[0]->getHeaderLine('x-ms-copy-action'));
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[0]->getHeaderLine('x-ms-lease-id'));
        self::assertSame('', $requests[0]->getHeaderLine('If-Match'));
        self::assertSame('', $requests[0]->getHeaderLine('If-Modified-Since'));
        self::assertSame('', $requests[0]->getHeaderLine('If-None-Match'));
        self::assertSame('', $requests[0]->getHeaderLine('If-Unmodified-Since'));
    }

    #[Test]
    public function tag_operations_send_conditions(): void
    {
        Server::enqueue([
            new Response(200),
            new Response(200, body: <<<'XML'
<Tags><TagSet><Tag><Key>foo</Key><Value>bar</Value></Tag></TagSet></Tags>
XML),
            new Response(501),
        ]);

        $conditions = new BlobRequestConditions(
            ifMatch: new ETag('"match"'),
            leaseId: '11111111-1111-4111-8111-111111111111',
        );

        $this->blob->setTags(['foo' => 'bar'], new SetBlobTagsOptions($conditions));
        $tags = $this->blob->getTags(new GetBlobTagsOptions($conditions));

        $requests = Server::received();

        self::assertSame(['foo' => 'bar'], $tags);
        self::assertCount(2, $requests);
        foreach ($requests as $request) {
            self::assertSame('"match"', $request->getHeaderLine('If-Match'));
            self::assertSame('11111111-1111-4111-8111-111111111111', $request->getHeaderLine('x-ms-lease-id'));
        }
    }
}
