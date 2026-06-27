<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Feature;

use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Models\BlobHttpHeaders;
use AzureOss\Storage\Blob\Models\BlobServiceClientOptions;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Common\ApiVersion;
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
    public function requests_use_latest_azurite_api_version_for_development_uri_by_default(): void
    {
        Server::enqueue([
            new Response(200, [
                'Content-Length' => '0',
                'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT',
            ]),
        ]);

        $this->blob->downloadStreaming();

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame(ApiVersion::latestAzurite()->value, $requests[0]->getHeaderLine('x-ms-version'));
    }

    #[Test]
    public function requests_use_latest_azurite_api_version_for_development_uri_when_configured_version_is_null(): void
    {
        Server::enqueue([
            new Response(200, [
                'Content-Length' => '0',
                'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT',
            ]),
        ]);

        /** @phpstan-ignore-next-line */
        $uri = new Uri(Server::$url.'/devstoreaccount1');
        $service = new BlobServiceClient($uri, options: new BlobServiceClientOptions(
            apiVersion: null,
        ));
        $service->getContainerClient('test')->getBlobClient('test')->downloadStreaming();

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame(ApiVersion::latestAzurite()->value, $requests[0]->getHeaderLine('x-ms-version'));
    }

    #[Test]
    public function requests_use_configured_api_version(): void
    {
        Server::enqueue([
            new Response(200, [
                'Content-Length' => '0',
                'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT',
            ]),
        ]);

        /** @phpstan-ignore-next-line */
        $uri = new Uri(Server::$url.'/devstoreaccount1');
        $service = new BlobServiceClient($uri, options: new BlobServiceClientOptions(
            apiVersion: ApiVersion::V2024_08_04,
        ));
        $service->getContainerClient('test')->getBlobClient('test')->downloadStreaming();

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame(ApiVersion::V2024_08_04->value, $requests[0]->getHeaderLine('x-ms-version'));
    }

    #[Test]
    public function upload_single_sends_correct_amount_of_requests(): void
    {
        Server::enqueue([
            new Response(200), // only one request
            new Response(501), // fail if more requests
        ]);

        $file = $this->tempFile(1000);
        $this->blob->upload($file, new UploadBlobOptions(
            initialTransferSize: 2000,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

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
        $this->blob->upload($file, new UploadBlobOptions(
            initialTransferSize: 0,
            maximumTransferSize: 5_000_000,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

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
    public function upload_parallel_blocks_sends_correct_amount_of_requests_for_small_files(): void
    {
        Server::enqueue([
            ...array_fill(0, 2, new Response(200)), // 1 chunks + 1 commit request
            new Response(501), // fail if more requests
        ]);

        $file = $this->tempFile(50_000);
        $this->blob->upload($file, new UploadBlobOptions(
            initialTransferSize: 0,
            maximumTransferSize: 8_000_000,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

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

        $this->blob->upload($stream, new UploadBlobOptions(
            initialTransferSize: 0,
            maximumTransferSize: 5_000_000,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

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

        $this->blob->upload($stream, new UploadBlobOptions(
            initialTransferSize: 0,
            maximumTransferSize: null,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

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
        $this->blob->upload($file, new UploadBlobOptions(
            initialTransferSize: 1000,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

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

        $this->blob->upload($stream, new UploadBlobOptions(
            initialTransferSize: 0,
            maximumTransferSize: 5_000_000,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

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
            initialTransferSize: 0,
            maximumTransferSize: 8_000_000,
            httpHeaders: new BlobHttpHeaders(
                contentHash: $contentHash,
                contentType: 'text/plain',
            ),
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

        $this->blob->upload($stream, new UploadBlobOptions(
            initialTransferSize: 0,
            maximumTransferSize: null,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
        ));

        $requests = Server::received();

        self::assertCount(2, $requests);
        self::assertSame('8000001', $requests[0]->getHeaderLine('Content-Length'));
        parse_str($requests[1]->getUri()->getQuery(), $query);
        self::assertSame('blocklist', $query['comp'] ?? null);
    }
}
