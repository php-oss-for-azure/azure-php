<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Blob\Functional;

use AzureOss\Identity\AccessToken;
use AzureOss\Identity\TokenCredential;
use AzureOss\Identity\TokenRequestContext;
use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Exceptions\BlobBatchException;
use AzureOss\Storage\Blob\Exceptions\BlobStorageException;
use AzureOss\Storage\Blob\Models\AbortCopyFromUriOptions;
use AzureOss\Storage\Blob\Models\AcquireBlobLeaseOptions;
use AzureOss\Storage\Blob\Models\BlobContainerInclude;
use AzureOss\Storage\Blob\Models\BlobErrorCode;
use AzureOss\Storage\Blob\Models\BlobHttpHeaders;
use AzureOss\Storage\Blob\Models\BlobInclude;
use AzureOss\Storage\Blob\Models\BlobRequestConditions;
use AzureOss\Storage\Blob\Models\BlobServiceClientOptions;
use AzureOss\Storage\Blob\Models\CreateSnapshotOptions;
use AzureOss\Storage\Blob\Models\DeleteBlobOptions;
use AzureOss\Storage\Blob\Models\DeleteContainerOptions;
use AzureOss\Storage\Blob\Models\DeleteSnapshotsOption;
use AzureOss\Storage\Blob\Models\GetBlobContainersOptions;
use AzureOss\Storage\Blob\Models\GetBlobsOptions;
use AzureOss\Storage\Blob\Models\GetBlobTagsOptions;
use AzureOss\Storage\Blob\Models\GetContainerPropertiesOptions;
use AzureOss\Storage\Blob\Models\SetBlobTagsOptions;
use AzureOss\Storage\Blob\Models\SetContainerMetadataOptions;
use AzureOss\Storage\Blob\Models\StageBlockOptions;
use AzureOss\Storage\Blob\Models\StartCopyFromUriOptions;
use AzureOss\Storage\Blob\Models\SyncCopyFromUriOptions;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use AzureOss\Storage\Blob\Sas\BlobSasPermissions;
use AzureOss\Storage\Blob\Specialized\BlobBatchClient;
use AzureOss\Storage\Blob\Specialized\BlockBlobClient;
use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Models\ETag;
use AzureOss\Tests\Storage\CreatesTempFiles;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Server\Server;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class BlobClientTest extends TestCase
{
    use CreatesTempFiles;

    private const BATCH_RESPONSE_BOUNDARY = 'batchresponse_66925647-d0cb-4109-b6d3-28efe3e1e5ed';

    private const DEVSTORE_ACCOUNT_KEY = 'Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==';

    private BlobClient $blob;

    protected function setUp(): void
    {
        Server::start();

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
    public function get_blobs_sends_includes(): void
    {
        Server::enqueue([
            new Response(200, body: '<EnumerationResults><Blobs/><NextMarker/></EnumerationResults>'),
            new Response(501),
        ]);

        $serverUrl = Server::$url;
        $container = (new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1')))->getContainerClient('test');

        iterator_to_array($container->getBlobs(options: new GetBlobsOptions(includes: [
            BlobInclude::SNAPSHOTS,
            BlobInclude::METADATA,
            BlobInclude::UNCOMMITTED_BLOBS,
            BlobInclude::TAGS,
            BlobInclude::VERSIONS,
        ])));

        $requests = Server::received();
        parse_str($requests[0]->getUri()->getQuery(), $query);

        self::assertSame('snapshots,metadata,uncommittedblobs,tags,versions', $query['include'] ?? null);
    }

    #[Test]
    public function get_blobs_by_hierarchy_sends_includes(): void
    {
        Server::enqueue([
            new Response(200, body: '<EnumerationResults><Blobs/><NextMarker/></EnumerationResults>'),
            new Response(501),
        ]);

        $serverUrl = Server::$url;
        $container = (new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1')))->getContainerClient('test');

        iterator_to_array($container->getBlobsByHierarchy(options: new GetBlobsOptions(includes: [
            BlobInclude::COPY,
            BlobInclude::DELETED,
            BlobInclude::DELETED_WITH_VERSIONS,
        ])));

        $requests = Server::received();
        parse_str($requests[0]->getUri()->getQuery(), $query);

        self::assertSame('copy,deleted,deletedwithversions', $query['include'] ?? null);
    }

    #[Test]
    public function delete_blob_sends_snapshot_option(): void
    {
        Server::enqueue([new Response(202), new Response(501)]);

        $this->blob->delete(new DeleteBlobOptions(
            snapshotsOption: DeleteSnapshotsOption::INCLUDE_SNAPSHOTS,
        ));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('DELETE', $requests[0]->getMethod());
        self::assertSame('include', $requests[0]->getHeaderLine('x-ms-delete-snapshots'));
    }

    #[Test]
    public function blob_clients_can_target_snapshots_and_versions_without_losing_sas_parameters(): void
    {
        $blob = new BlobClient(new Uri(
            'https://account.blob.core.windows.net/container/blob?sv=2024-08-04&sig=signature',
        ));

        $snapshot = $blob->withSnapshot('2026-06-28T10:20:30.1234567Z');
        parse_str($snapshot->uri->getQuery(), $snapshotQuery);

        self::assertSame('2026-06-28T10:20:30.1234567Z', $snapshotQuery['snapshot'] ?? null);
        self::assertSame('signature', $snapshotQuery['sig'] ?? null);
        self::assertArrayNotHasKey('snapshot', $this->query($blob->uri));

        $version = $snapshot->withVersion('2026-06-28T11:20:30.1234567Z');
        $versionQuery = $this->query($version->uri);

        self::assertSame('2026-06-28T11:20:30.1234567Z', $versionQuery['versionid'] ?? null);
        self::assertArrayNotHasKey('snapshot', $versionQuery);

        $base = $version->withVersion(null);
        self::assertArrayNotHasKey('versionid', $this->query($base->uri));
        self::assertSame('signature', $this->query($base->uri)['sig'] ?? null);
    }

    #[Test]
    public function selected_blob_clients_generate_resource_specific_sas_uris(): void
    {
        $blob = new BlobClient(
            new Uri('https://account.blob.core.windows.net/container/blob?custom=value'),
            new StorageSharedKeyCredential('account', base64_encode(str_repeat('x', 32))),
        );
        $expiresOn = new \DateTimeImmutable('2030-01-01T00:00:00Z');

        $snapshotUri = $blob
            ->withSnapshot('2026-06-28T10:20:30.1234567Z')
            ->generateSasUri(BlobSasBuilder::new()
                ->setPermissions(new BlobSasPermissions(read: true))
                ->setExpiresOn($expiresOn));
        $snapshotQuery = $this->query($snapshotUri);

        self::assertSame(1, substr_count((string) $snapshotUri, '?'));
        self::assertSame('2026-06-28T10:20:30.1234567Z', $snapshotQuery['snapshot'] ?? null);
        self::assertSame('bs', $snapshotQuery['sr'] ?? null);
        self::assertSame('value', $snapshotQuery['custom'] ?? null);
        self::assertArrayNotHasKey('sst', $snapshotQuery);

        $versionUri = $blob
            ->withVersion('2026-06-28T11:20:30.1234567Z')
            ->generateSasUri(BlobSasBuilder::new()
                ->setPermissions(new BlobSasPermissions(read: true))
                ->setExpiresOn($expiresOn));
        $versionQuery = $this->query($versionUri);

        self::assertSame(1, substr_count((string) $versionUri, '?'));
        self::assertSame('2026-06-28T11:20:30.1234567Z', $versionQuery['versionid'] ?? null);
        self::assertSame('bv', $versionQuery['sr'] ?? null);
        self::assertSame('value', $versionQuery['custom'] ?? null);
    }

    #[Test]
    public function block_blob_clients_can_target_snapshots_and_versions(): void
    {
        $blockBlob = new BlockBlobClient(new Uri('https://account.blob.core.windows.net/container/blob'));

        self::assertSame('snapshot-id', $this->query($blockBlob->withSnapshot('snapshot-id')->uri)['snapshot'] ?? null);
        self::assertSame('version-id', $this->query($blockBlob->withVersion('version-id')->uri)['versionid'] ?? null);
    }

    #[Test]
    public function selected_blob_request_combines_the_selector_with_operation_query_parameters(): void
    {
        Server::enqueue([
            new Response(200, body: '<Tags><TagSet/></Tags>'),
            new Response(501),
        ]);

        $this->blob->withVersion('version-id')->getTags();

        $requests = Server::received();
        $query = $this->query($requests[0]->getUri());

        self::assertCount(1, $requests);
        self::assertSame('tags', $query['comp'] ?? null);
        self::assertSame('version-id', $query['versionid'] ?? null);
    }

    #[Test]
    public function create_snapshot_sends_options_and_deserializes_the_response(): void
    {
        Server::enqueue([
            new Response(201, [
                'x-ms-snapshot' => '2026-06-28T10:20:30.1234567Z',
                'ETag' => '"snapshot-etag"',
                'Last-Modified' => 'Sun, 28 Jun 2026 10:20:30 GMT',
                'x-ms-version-id' => 'version-id',
                'x-ms-request-server-encrypted' => 'true',
            ]),
            new Response(501),
        ]);

        $info = $this->blob->createSnapshot(new CreateSnapshotOptions(
            metadata: ['purpose' => 'backup'],
            conditions: new BlobRequestConditions(ifMatch: new ETag('"etag"')),
        ));

        $requests = Server::received();
        $query = $this->query($requests[0]->getUri());

        self::assertCount(1, $requests);
        self::assertSame('PUT', $requests[0]->getMethod());
        self::assertSame('snapshot', $query['comp'] ?? null);
        self::assertSame('backup', $requests[0]->getHeaderLine('x-ms-meta-purpose'));
        self::assertSame('"etag"', $requests[0]->getHeaderLine('If-Match'));
        self::assertSame('2026-06-28T10:20:30.1234567Z', $info->snapshot);
        self::assertSame('version-id', $info->versionId);
    }

    #[Test]
    public function undelete_blob_sends_correct_request(): void
    {
        Server::enqueue([new Response(200), new Response(501)]);

        $this->blob->undelete();

        $requests = Server::received();
        parse_str($requests[0]->getUri()->getQuery(), $query);

        self::assertCount(1, $requests);
        self::assertSame('PUT', $requests[0]->getMethod());
        self::assertSame('undelete', $query['comp'] ?? null);
    }

    /**
     * @return array<string, string>
     */
    private function query(UriInterface $uri): array
    {
        parse_str($uri->getQuery(), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    #[Test]
    public function get_blob_containers_sends_options(): void
    {
        Server::enqueue([
            new Response(200, body: '<EnumerationResults><Containers/><NextMarker/></EnumerationResults>'),
            new Response(501),
        ]);

        $serverUrl = Server::$url;
        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));

        iterator_to_array($service->getBlobContainers('docs-', new GetBlobContainersOptions(
            pageSize: 25,
            includes: [
                BlobContainerInclude::METADATA,
                BlobContainerInclude::DELETED,
                BlobContainerInclude::SYSTEM,
            ],
        )));

        $requests = Server::received();
        parse_str($requests[0]->getUri()->getQuery(), $query);

        self::assertCount(1, $requests);
        self::assertSame('docs-', $query['prefix'] ?? null);
        self::assertSame('25', $query['maxresults'] ?? null);
        self::assertSame('metadata,deleted,system', $query['include'] ?? null);
    }

    #[Test]
    public function undelete_blob_container_sends_correct_request_and_returns_client(): void
    {
        Server::enqueue([new Response(201), new Response(501)]);

        $serverUrl = Server::$url;
        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));

        $container = $service->undeleteBlobContainer('photos', '01D9A8BY7Q4Y4J');

        $requests = Server::received();
        parse_str($requests[0]->getUri()->getQuery(), $query);

        self::assertCount(1, $requests);
        self::assertSame('PUT', $requests[0]->getMethod());
        self::assertStringEndsWith('/photos', $requests[0]->getUri()->getPath());
        self::assertSame('container', $query['restype'] ?? null);
        self::assertSame('undelete', $query['comp'] ?? null);
        self::assertSame('photos', $requests[0]->getHeaderLine('x-ms-deleted-container-name'));
        self::assertSame('01D9A8BY7Q4Y4J', $requests[0]->getHeaderLine('x-ms-deleted-container-version'));
        self::assertSame('photos', $container->containerName);
    }

    #[Test]
    public function lease_clients_inherit_configured_api_version(): void
    {
        Server::enqueue([
            new Response(201, ['x-ms-lease-id' => '11111111-1111-4111-8111-111111111111']),
            new Response(201, ['x-ms-lease-id' => '22222222-2222-4222-8222-222222222222']),
        ]);

        $uri = new Uri(Server::$url.'/devstoreaccount1');
        $service = new BlobServiceClient($uri, options: new BlobServiceClientOptions(
            apiVersion: ApiVersion::V2024_08_04,
        ));
        $container = $service->getContainerClient('test');

        $container->getBlobLeaseClient()->acquire();
        $container->getBlobClient('test')->getBlobLeaseClient()->acquire();

        $requests = Server::received();

        self::assertCount(2, $requests);
        self::assertSame(ApiVersion::V2024_08_04->value, $requests[0]->getHeaderLine('x-ms-version'));
        self::assertSame(ApiVersion::V2024_08_04->value, $requests[1]->getHeaderLine('x-ms-version'));
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
    public function upload_parallel_blocks_sends_only_lease_id_to_stage_block_and_all_conditions_to_commit(): void
    {
        Server::enqueue([
            new Response(200),
            new Response(200),
            new Response(501),
        ]);

        $this->blob->upload('test', new UploadBlobOptions(
            initialTransferSize: 0,
            maximumTransferSize: 8_000_000,
            httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'),
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
            protected StreamInterface $stream;

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
            protected StreamInterface $stream;

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

        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));
        $container = $service->getContainerClient('test');

        $container->getProperties(new GetContainerPropertiesOptions(
            conditions: new BlobRequestConditions(
                leaseId: '11111111-1111-4111-8111-111111111111',
            ),
        ));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('HEAD', $requests[0]->getMethod());
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[0]->getHeaderLine('x-ms-lease-id'));
    }

    #[Test]
    public function container_get_properties_rejects_unsupported_conditions(): void
    {
        $serverUrl = Server::$url;

        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));
        $container = $service->getContainerClient('test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BlobContainerClient::getProperties does not support request condition(s): ifMatch.');

        $container->getProperties(new GetContainerPropertiesOptions(
            conditions: new BlobRequestConditions(ifMatch: new ETag('"match"')),
        ));
    }

    #[Test]
    public function container_delete_sends_supported_conditions(): void
    {
        Server::enqueue([
            new Response(202),
            new Response(501),
        ]);

        $serverUrl = Server::$url;

        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));
        $container = $service->getContainerClient('test');

        $container->delete(new DeleteContainerOptions(new BlobRequestConditions(
            ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC'),
            ifUnmodifiedSince: new \DateTimeImmutable('2025-01-02 12:34:56 UTC'),
            leaseId: '11111111-1111-4111-8111-111111111111',
        )));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('Wed, 01 Jan 2025 12:34:56 GMT', $requests[0]->getHeaderLine('If-Modified-Since'));
        self::assertSame('Thu, 02 Jan 2025 12:34:56 GMT', $requests[0]->getHeaderLine('If-Unmodified-Since'));
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[0]->getHeaderLine('x-ms-lease-id'));
    }

    #[Test]
    public function container_delete_rejects_unsupported_conditions(): void
    {
        $serverUrl = Server::$url;

        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));
        $container = $service->getContainerClient('test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BlobContainerClient::delete does not support request condition(s): ifMatch, ifNoneMatch.');

        $container->delete(new DeleteContainerOptions(new BlobRequestConditions(
            ifMatch: new ETag('"match"'),
            ifNoneMatch: ETag::all(),
        )));
    }

    #[Test]
    public function container_set_metadata_sends_supported_conditions(): void
    {
        Server::enqueue([
            new Response(200),
            new Response(501),
        ]);

        $serverUrl = Server::$url;

        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));
        $container = $service->getContainerClient('test');

        $container->setMetadata(['foo' => 'bar'], new SetContainerMetadataOptions(new BlobRequestConditions(
            ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC'),
            leaseId: '11111111-1111-4111-8111-111111111111',
        )));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('Wed, 01 Jan 2025 12:34:56 GMT', $requests[0]->getHeaderLine('If-Modified-Since'));
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[0]->getHeaderLine('x-ms-lease-id'));
    }

    #[Test]
    public function container_set_metadata_rejects_unsupported_conditions(): void
    {
        $serverUrl = Server::$url;

        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));
        $container = $service->getContainerClient('test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BlobContainerClient::setMetadata does not support request condition(s): ifUnmodifiedSince.');

        $container->setMetadata(['foo' => 'bar'], new SetContainerMetadataOptions(new BlobRequestConditions(
            ifUnmodifiedSince: new \DateTimeImmutable('2025-01-02 12:34:56 UTC'),
        )));
    }

    #[Test]
    public function container_lease_operations_send_supported_conditions(): void
    {
        Server::enqueue([
            new Response(201, ['x-ms-lease-id' => '22222222-2222-4222-8222-222222222222']),
            new Response(501),
        ]);

        $serverUrl = Server::$url;

        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));
        $container = $service->getContainerClient('test');

        $container->getBlobLeaseClient()->acquire(15, new AcquireBlobLeaseOptions(new BlobRequestConditions(
            ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC'),
            ifUnmodifiedSince: new \DateTimeImmutable('2025-01-02 12:34:56 UTC'),
        )));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('Wed, 01 Jan 2025 12:34:56 GMT', $requests[0]->getHeaderLine('If-Modified-Since'));
        self::assertSame('Thu, 02 Jan 2025 12:34:56 GMT', $requests[0]->getHeaderLine('If-Unmodified-Since'));
        self::assertSame('', $requests[0]->getHeaderLine('x-ms-lease-id'));
    }

    #[Test]
    public function container_lease_operations_reject_unsupported_conditions(): void
    {
        $serverUrl = Server::$url;

        $service = new BlobServiceClient(new Uri($serverUrl.'/devstoreaccount1'));
        $container = $service->getContainerClient('test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BlobLeaseClient::acquire does not support request condition(s): ifMatch, leaseId.');

        $container->getBlobLeaseClient()->acquire(15, new AcquireBlobLeaseOptions(new BlobRequestConditions(
            ifMatch: new ETag('"match"'),
            leaseId: '11111111-1111-4111-8111-111111111111',
        )));
    }

    #[Test]
    public function abort_copy_from_uri_sends_lease_id_condition(): void
    {
        Server::enqueue([
            new Response(204),
            new Response(501),
        ]);

        $this->blob->abortCopyFromUri('copy-id', new AbortCopyFromUriOptions(new BlobRequestConditions(
            leaseId: '11111111-1111-4111-8111-111111111111',
        )));

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertSame('abort', $requests[0]->getHeaderLine('x-ms-copy-action'));
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[0]->getHeaderLine('x-ms-lease-id'));
    }

    #[Test]
    public function abort_copy_from_uri_rejects_unsupported_conditions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BlobClient::abortCopyFromUri does not support request condition(s): ifMatch.');

        $this->blob->abortCopyFromUri('copy-id', new AbortCopyFromUriOptions(new BlobRequestConditions(
            ifMatch: new ETag('"match"'),
        )));
    }

    #[Test]
    public function start_copy_from_uri_rejects_source_lease_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BlobClient::startCopyFromUri source does not support request condition(s): leaseId.');

        $this->blob->startCopyFromUri(
            new Uri('https://example.com/source'),
            new StartCopyFromUriOptions(
                sourceConditions: new BlobRequestConditions(
                    leaseId: '11111111-1111-4111-8111-111111111111',
                ),
            ),
        );
    }

    #[Test]
    public function sync_copy_from_uri_rejects_source_lease_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BlobClient::syncCopyFromUri source does not support request condition(s): leaseId.');

        $this->blob->syncCopyFromUri(
            new Uri('https://example.com/source'),
            new SyncCopyFromUriOptions(
                sourceConditions: new BlobRequestConditions(
                    leaseId: '11111111-1111-4111-8111-111111111111',
                ),
            ),
        );
    }

    #[Test]
    public function stage_block_rejects_non_lease_conditions(): void
    {
        $blockBlob = new BlockBlobClient($this->blob->uri);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BlockBlobClient::stageBlock does not support request condition(s): ifMatch.');

        $blockBlob->stageBlock(
            base64_encode('block-1'),
            'content',
            new StageBlockOptions(new BlobRequestConditions(ifMatch: new ETag('"match"'))),
        );
    }

    #[Test]
    public function released_and_broken_lease_results_do_not_fall_back_to_client_lease_id(): void
    {
        Server::enqueue([
            new Response(200, [
                'ETag' => '"released"',
                'Last-Modified' => 'Wed, 01 Jan 2025 12:34:56 GMT',
            ]),
            new Response(202, ['x-ms-lease-time' => '0']),
            new Response(501),
        ]);

        $leaseClient = $this->blob->getBlobLeaseClient('11111111-1111-4111-8111-111111111111');

        $released = $leaseClient->release();
        $broken = $leaseClient->break(0);

        self::assertSame('"released"', (string) $released->eTag);
        self::assertSame('Wed, 01 Jan 2025 12:34:56 GMT', $released->lastModified->format('D, d M Y H:i:s T'));
        self::assertNull($broken->leaseId);
        self::assertSame(0, $broken->leaseTime);
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

    #[Test]
    public function delete_blobs_sends_signed_sub_requests(): void
    {
        Server::enqueue([self::batchResponse([202, 202]), new Response(501)]);

        $this->batchContainer(new StorageSharedKeyCredential('devstoreaccount1', self::DEVSTORE_ACCOUNT_KEY))
            ->getBlobBatchClient()
            ->deleteBlobs(['blob one.txt', 'nested/ünï+.txt']);

        $request = self::singleReceivedRequest();
        $parts = self::batchSubRequests($request);

        self::assertSame('POST', $request->getMethod());
        self::assertSame(['restype' => 'container', 'comp' => 'batch'], $this->query($request->getUri()));
        self::assertCount(2, $parts);
        self::assertStringContainsString("Content-ID: 0\r\n", $parts[0]);
        self::assertStringContainsString("DELETE /devstoreaccount1/test/blob%20one.txt HTTP/1.1\r\n", $parts[0]);
        self::assertStringContainsString("Content-ID: 1\r\n", $parts[1]);
        self::assertStringContainsString("DELETE /devstoreaccount1/test/nested/%C3%BCn%C3%AF+.txt HTTP/1.1\r\n", $parts[1]);

        foreach (['/devstoreaccount1/test/blob%20one.txt', '/devstoreaccount1/test/nested/%C3%BCn%C3%AF+.txt'] as $i => $path) {
            self::assertSame(1, preg_match('/^x-ms-date: (.+ GMT)\r$/m', $parts[$i], $date));
            self::assertStringContainsString('Authorization: '.self::sharedKeyAuthorization("DELETE\n".str_repeat("\n", 11)."x-ms-date:{$date[1]}\n/devstoreaccount1{$path}")."\r\n", $parts[$i]);
            self::assertStringNotContainsStringIgnoringCase('x-ms-version', $parts[$i]);
            self::assertStringNotContainsStringIgnoringCase('Host:', $parts[$i]);
        }
    }

    #[Test]
    public function service_batch_client_sends_blob_paths(): void
    {
        Server::enqueue([self::batchResponse([202, 202]), new Response(501)]);

        $service = new BlobServiceClient(self::serverUri('/devstoreaccount1'));
        $service->getBlobBatchClient()->deleteBlobs([
            'first/a.txt',
            $service->getContainerClient('second')->getBlobClient('b.txt'),
        ]);

        $request = self::singleReceivedRequest();
        $parts = self::batchSubRequests($request);

        self::assertSame(['comp' => 'batch'], $this->query($request->getUri()));
        self::assertStringContainsString("DELETE /devstoreaccount1/first/a.txt HTTP/1.1\r\n", $parts[0]);
        self::assertStringContainsString("DELETE /devstoreaccount1/second/b.txt HTTP/1.1\r\n", $parts[1]);
    }

    #[Test]
    public function delete_blobs_signs_sub_requests_with_token_credential(): void
    {
        Server::enqueue([self::batchResponse([202, 202]), new Response(501)]);

        $credential = new class implements TokenCredential
        {
            public function getToken(TokenRequestContext $context): AccessToken
            {
                return new AccessToken('token', new \DateTimeImmutable('+1 hour'), 'Bearer');
            }
        };

        $this->batchContainer($credential)->getBlobBatchClient()->deleteBlobs(['a.txt', 'b.txt']);

        foreach (self::batchSubRequests(self::singleReceivedRequest()) as $part) {
            self::assertStringContainsString("Authorization: Bearer token\r\n", $part);
        }
    }

    #[Test]
    public function delete_blobs_fetches_token_once(): void
    {
        Server::enqueue([self::batchResponse([202, 202]), new Response(501)]);

        $credential = new class implements TokenCredential
        {
            public int $calls = 0;

            public function getToken(TokenRequestContext $context): AccessToken
            {
                $this->calls++;

                return new AccessToken('token', new \DateTimeImmutable('+1 hour'), 'Bearer');
            }
        };

        $this->batchContainer($credential)->getBlobBatchClient()->deleteBlobs(['a.txt', 'b.txt']);

        $request = self::singleReceivedRequest();

        self::assertSame('Bearer token', $request->getHeaderLine('Authorization'));
        self::assertSame(2, substr_count((string) $request->getBody(), "Authorization: Bearer token\r\n"));
        self::assertSame(1, $credential->calls);
    }

    #[Test]
    public function delete_blobs_appends_sas_to_sub_requests(): void
    {
        Server::enqueue([self::batchResponse([202, 202, 202]), new Response(501)]);

        $container = new BlobContainerClient(self::serverUri('/devstoreaccount1/test?sv=2025-11-05&sp=d&sig=a%2Bb%3D'));
        $container->getBlobBatchClient()->deleteBlobs([
            'a.txt',
            $container->getBlobClient('b.txt')->withSnapshot('2026-06-28T10:20:30.1234567Z'),
            $container->getBlobClient('c.txt')->withVersion('2026-06-28T10:20:30.7654321Z'),
        ]);

        $parts = self::batchSubRequests(self::singleReceivedRequest());

        self::assertStringContainsString("DELETE /devstoreaccount1/test/a.txt?sv=2025-11-05&sp=d&sig=a%2Bb%3D HTTP/1.1\r\n", $parts[0]);
        self::assertStringContainsString("DELETE /devstoreaccount1/test/b.txt?sv=2025-11-05&sp=d&sig=a%2Bb%3D&snapshot=2026-06-28T10:20:30.1234567Z HTTP/1.1\r\n", $parts[1]);
        self::assertStringContainsString("DELETE /devstoreaccount1/test/c.txt?sv=2025-11-05&sp=d&sig=a%2Bb%3D&versionid=2026-06-28T10:20:30.7654321Z HTTP/1.1\r\n", $parts[2]);

        foreach ($parts as $part) {
            self::assertStringNotContainsString('Authorization:', $part);
        }
    }

    #[Test]
    public function delete_blobs_sends_snapshots_option(): void
    {
        Server::enqueue([self::batchResponse([202, 202]), new Response(501)]);

        $this->batchContainer()->getBlobBatchClient()->deleteBlobs(['a.txt', 'b.txt'], DeleteSnapshotsOption::INCLUDE_SNAPSHOTS);

        foreach (self::batchSubRequests(self::singleReceivedRequest()) as $part) {
            self::assertStringContainsString("x-ms-delete-snapshots: include\r\n", $part);
        }
    }

    #[Test]
    public function delete_blobs_throws_batch_exception_for_failed_sub_requests(): void
    {
        Server::enqueue([self::batchResponse([202, 404, 412]), new Response(501)]);

        try {
            $this->batchContainer()->getBlobBatchClient()->deleteBlobs(['a.txt', 'missing.txt', 'leased.txt']);

            self::fail('Expected the batch to fail.');
        } catch (BlobBatchException $e) {
            self::assertSame([1, 2], array_column($e->failures, 'index'));
            self::assertSame(['missing.txt', 'leased.txt'], array_column($e->failures, 'blob'));
            self::assertSame(BlobErrorCode::BlobNotFound, $e->failures[0]->exception->errorCode);
            self::assertSame(404, $e->failures[0]->exception->statusCode);
            self::assertSame('request-1', $e->failures[0]->exception->requestId);
            self::assertSame('The specified blob does not exist.', $e->failures[0]->exception->getMessage());
            self::assertSame(BlobErrorCode::LeaseIdMissing, $e->failures[1]->exception->errorCode);
            self::assertSame(412, $e->failures[1]->exception->statusCode);
            self::assertSame($e->failures[0]->exception, $e->getPrevious());
            self::assertSame('batch-request', $e->requestId);
            self::assertSame(202, $e->statusCode);
            self::assertSame(
                '2 of 3 blob batch sub-requests failed: "/devstoreaccount1/test/missing.txt" (BlobNotFound), "/devstoreaccount1/test/leased.txt" (LeaseIdMissing)',
                $e->getMessage(),
            );
        }
    }

    #[Test]
    public function delete_blobs_throws_storage_exception_for_rejected_batch(): void
    {
        Server::enqueue([
            new Response(403, ['x-ms-error-code' => 'AuthenticationFailed'], '<Error><Code>AuthenticationFailed</Code><Message>Signature did not match.</Message></Error>'),
            new Response(501),
        ]);

        try {
            $this->batchContainer()->getBlobBatchClient()->deleteBlobs(['a.txt']);

            self::fail('Expected the batch to fail.');
        } catch (BlobStorageException $e) {
            self::assertSame(BlobErrorCode::AuthenticationFailed, $e->errorCode);
            self::assertSame(403, $e->statusCode);
        }
    }

    #[Test]
    public function delete_blobs_throws_storage_exception_for_batch_rejected_in_body(): void
    {
        Server::enqueue([
            new Response(202, ['Content-Type' => 'multipart/mixed; boundary='.self::BATCH_RESPONSE_BOUNDARY, 'x-ms-request-id' => 'batch-request'], '--'.self::BATCH_RESPONSE_BOUNDARY."\r\nContent-Type: application/http\r\n\r\n"
                ."HTTP/1.1 400 One of the request inputs is not valid.\r\nx-ms-error-code: InvalidInput\r\nx-ms-request-id: request-0\r\nContent-Type: application/xml\r\n\r\n"
                ."<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n<Error><Code>InvalidInput</Code><Message>One of the request inputs is not valid.</Message></Error>\r\n"
                .'--'.self::BATCH_RESPONSE_BOUNDARY.'--'),
            new Response(501),
        ]);

        try {
            $this->batchContainer()->getBlobBatchClient()->deleteBlobs(['a.txt', 'b.txt']);

            self::fail('Expected the batch to be rejected.');
        } catch (BlobStorageException $e) {
            self::assertNotInstanceOf(BlobBatchException::class, $e);
            self::assertSame(BlobErrorCode::InvalidInput, $e->errorCode);
            self::assertSame('One of the request inputs is not valid.', $e->getMessage());
            self::assertSame('request-0', $e->requestId);
            self::assertSame(400, $e->statusCode);
        }
    }

    #[Test]
    public function delete_blobs_rejects_empty_list(): void
    {
        Server::enqueue([new Response(501)]);

        try {
            $this->batchContainer()->getBlobBatchClient()->deleteBlobs([]);

            self::fail('Expected deleting no blobs to fail.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Cannot submit an empty batch.', $e->getMessage());
        }

        self::assertSame([], Server::received());
    }

    #[Test]
    public function submit_batch_sends_options_per_operation(): void
    {
        Server::enqueue([self::batchResponse([202, 202, 202]), new Response(501)]);

        $batchClient = $this->batchContainer()->getBlobBatchClient();
        $batch = $batchClient->createBatch();
        $batch->deleteBlob('a.txt', new DeleteBlobOptions(
            conditions: new BlobRequestConditions(leaseId: 'lease-id'),
            snapshotsOption: DeleteSnapshotsOption::INCLUDE_SNAPSHOTS,
        ));
        $batch->deleteBlob('b.txt');
        $batch->deleteBlob('c.txt', new DeleteBlobOptions(snapshotsOption: DeleteSnapshotsOption::ONLY_SNAPSHOTS));

        $batchClient->submitBatch($batch);

        $parts = self::batchSubRequests(self::singleReceivedRequest());

        self::assertCount(3, $parts);
        self::assertStringContainsString("DELETE /devstoreaccount1/test/a.txt HTTP/1.1\r\n", $parts[0]);
        self::assertStringContainsString("x-ms-lease-id: lease-id\r\n", $parts[0]);
        self::assertStringContainsString("x-ms-delete-snapshots: include\r\n", $parts[0]);
        self::assertStringContainsString("DELETE /devstoreaccount1/test/b.txt HTTP/1.1\r\n", $parts[1]);
        self::assertStringNotContainsString('x-ms-lease-id', $parts[1]);
        self::assertStringNotContainsString('x-ms-delete-snapshots', $parts[1]);
        self::assertStringContainsString("DELETE /devstoreaccount1/test/c.txt HTTP/1.1\r\n", $parts[2]);
        self::assertStringNotContainsString('x-ms-lease-id', $parts[2]);
        self::assertStringContainsString("x-ms-delete-snapshots: only\r\n", $parts[2]);
    }

    #[Test]
    public function submit_batch_accepts_batch_from_earlier_batch_client(): void
    {
        Server::enqueue([self::batchResponse([202]), new Response(501)]);

        $container = $this->batchContainer();
        $batch = $container->getBlobBatchClient()->createBatch();
        $batch->deleteBlob('a.txt');

        $container->getBlobBatchClient()->submitBatch($batch);

        self::assertStringContainsString("DELETE /devstoreaccount1/test/a.txt HTTP/1.1\r\n", self::batchSubRequests(self::singleReceivedRequest())[0]);
    }

    #[Test]
    public function submit_batch_accepts_batch_from_client_with_same_uri_and_credential(): void
    {
        Server::enqueue([self::batchResponse([202]), new Response(501)]);

        $batch = (new BlobBatchClient(self::serverUri('/devstoreaccount1/test'), containerName: 'test'))->createBatch();
        $batch->deleteBlob('a.txt');

        (new BlobBatchClient(self::serverUri('/devstoreaccount1/test'), containerName: 'test'))->submitBatch($batch);

        self::assertSame(['restype' => 'container', 'comp' => 'batch'], $this->query(self::singleReceivedRequest()->getUri()));
    }

    #[Test]
    public function submit_batch_rejects_batch_from_client_with_another_uri(): void
    {
        Server::enqueue([new Response(501)]);

        $batch = $this->batchContainer()->getBlobBatchClient()->createBatch();
        $batch->deleteBlob('a.txt');

        try {
            (new BlobContainerClient(self::serverUri('/devstoreaccount1/other')))->getBlobBatchClient()->submitBatch($batch);

            self::fail('Expected submitting through another URI to fail.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('The batch was created for a batch client with another URI or credential.', $e->getMessage());
        }

        self::assertSame([], Server::received());
    }

    #[Test]
    public function submit_batch_rejects_submitted_batch(): void
    {
        Server::enqueue([self::batchResponse([202]), new Response(501)]);

        $batchClient = $this->batchContainer()->getBlobBatchClient();
        $batch = $batchClient->createBatch();
        $batch->deleteBlob('a.txt');
        $batchClient->submitBatch($batch);

        try {
            $batchClient->submitBatchAsync($batch);

            self::fail('Expected resubmitting the batch to fail.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('The batch has already been submitted.', $e->getMessage());
        }

        self::assertCount(1, Server::received());
    }

    #[Test]
    public function delete_blob_rejects_submitted_batch(): void
    {
        Server::enqueue([self::batchResponse([202]), new Response(501)]);

        $batchClient = $this->batchContainer()->getBlobBatchClient();
        $batch = $batchClient->createBatch();
        $batch->deleteBlob('a.txt');
        $batchClient->submitBatch($batch);

        try {
            $batch->deleteBlob('b.txt');

            self::fail('Expected adding to a submitted batch to fail.');
        } catch (\LogicException $e) {
            self::assertSame('The batch has already been submitted.', $e->getMessage());
        }

        self::assertCount(1, $batch);
    }

    #[Test]
    public function batch_failures_report_blobs_as_given(): void
    {
        Server::enqueue([self::batchResponse([202, 404, 404, 404]), new Response(501)]);

        $service = new BlobServiceClient(self::serverUri('/devstoreaccount1'));
        $snapshot = $service->getContainerClient('other')->getBlobClient('dir/b c.txt')->withSnapshot('2026-06-28T10:20:30.1234567Z');

        try {
            $service->getBlobBatchClient()->deleteBlobs(['first' => 'test/a.txt', 'second' => '/test/a b.txt', 'third' => $snapshot, 'fourth' => 'test/dir/'], DeleteSnapshotsOption::ONLY_SNAPSHOTS);

            self::fail('Expected the batch to fail.');
        } catch (BlobBatchException $e) {
            self::assertSame([1, 2, 3], array_column($e->failures, 'index'));
            self::assertSame(['/test/a b.txt', $snapshot, 'test/dir/'], array_column($e->failures, 'blob'));
            self::assertSame(
                '3 of 4 blob batch sub-requests failed: "/devstoreaccount1/test/a b.txt" (BlobNotFound), "/devstoreaccount1/other/dir/b c.txt?snapshot=2026-06-28T10:20:30.1234567Z" (BlobNotFound), "/devstoreaccount1/test/dir/" (BlobNotFound)',
                $e->getMessage(),
            );
        }
    }

    #[Test]
    public function batch_exception_message_names_at_most_five_blobs(): void
    {
        Server::enqueue([self::batchResponse(array_fill(0, 7, 404)), new Response(501)]);

        try {
            $this->batchContainer()->getBlobBatchClient()->deleteBlobs(array_map(static fn (int $i): string => "{$i}.txt", range(0, 6)));

            self::fail('Expected the batch to fail.');
        } catch (BlobBatchException $e) {
            self::assertCount(7, $e->failures);
            self::assertSame(
                '7 of 7 blob batch sub-requests failed: "/devstoreaccount1/test/0.txt" (BlobNotFound), "/devstoreaccount1/test/1.txt" (BlobNotFound), '
                .'"/devstoreaccount1/test/2.txt" (BlobNotFound), "/devstoreaccount1/test/3.txt" (BlobNotFound), "/devstoreaccount1/test/4.txt" (BlobNotFound) and 2 more',
                $e->getMessage(),
            );
        }
    }

    #[Test]
    public function delete_blobs_rejects_blob_client_from_another_container(): void
    {
        Server::enqueue([new Response(501)]);

        $service = new BlobServiceClient(self::serverUri('/devstoreaccount1'));

        try {
            $service->getContainerClient('test')->getBlobBatchClient()->deleteBlobs([
                $service->getContainerClient('other')->getBlobClient('a.txt'),
            ]);

            self::fail('Expected deleting a blob from another container to fail.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Blob "a.txt" is not in container "test".', $e->getMessage());
        }

        self::assertSame([], Server::received());
    }

    private function batchContainer(StorageSharedKeyCredential|TokenCredential|null $credential = null): BlobContainerClient
    {
        return new BlobContainerClient(self::serverUri('/devstoreaccount1/test'), $credential);
    }

    private static function serverUri(string $pathAndQuery): Uri
    {
        return new Uri(rtrim(Server::$url, '/').$pathAndQuery);
    }

    private static function singleReceivedRequest(): RequestInterface
    {
        $requests = Server::received();

        self::assertCount(1, $requests);

        return $requests[0];
    }

    /**
     * @return list<string>
     */
    private static function batchSubRequests(RequestInterface $request): array
    {
        self::assertSame(1, preg_match('/boundary=(batch_[0-9a-f]+)$/', $request->getHeaderLine('Content-Type'), $boundary));

        $parts = explode("--{$boundary[1]}", (string) $request->getBody());

        self::assertSame("--\r\n", array_pop($parts));
        self::assertSame('', array_shift($parts));

        return $parts;
    }

    private static function sharedKeyAuthorization(string $stringToSign): string
    {
        $key = base64_decode(self::DEVSTORE_ACCOUNT_KEY, true);

        self::assertIsString($key);

        return 'SharedKey devstoreaccount1:'.base64_encode(hash_hmac('sha256', $stringToSign, $key, true));
    }

    /**
     * @param  list<int>  $statuses
     */
    private static function batchResponse(array $statuses): Response
    {
        $parts = [
            202 => "HTTP/1.1 202 Accepted\r\nx-ms-delete-type-permanent: true\r\nx-ms-request-id: request-%d\r\nx-ms-version: 2026-06-06\r\n\r\n",
            404 => "HTTP/1.1 404 The specified blob does not exist.\r\nx-ms-error-code: BlobNotFound\r\nx-ms-request-id: request-%d\r\nx-ms-version: 2026-06-06\r\nContent-Length: 216\r\nContent-Type: application/xml\r\n\r\n"
                ."<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n<Error><Code>BlobNotFound</Code><Message>The specified blob does not exist.</Message></Error>\r\n",
            412 => "HTTP/1.1 412 There is currently a lease on the blob and no lease ID was specified in the request.\r\nx-ms-error-code: LeaseIdMissing\r\nx-ms-request-id: request-%d\r\nx-ms-version: 2026-06-06\r\n\r\n",
        ];

        $body = '';
        foreach ($statuses as $contentId => $status) {
            $body .= '--'.self::BATCH_RESPONSE_BOUNDARY."\r\nContent-Type: application/http\r\nContent-ID: {$contentId}\r\n\r\n".sprintf($parts[$status], $contentId);
        }

        return new Response(202, [
            'Content-Type' => 'multipart/mixed; boundary='.self::BATCH_RESPONSE_BOUNDARY,
            'x-ms-request-id' => 'batch-request',
        ], $body.'--'.self::BATCH_RESPONSE_BOUNDARY.'--');
    }
}
