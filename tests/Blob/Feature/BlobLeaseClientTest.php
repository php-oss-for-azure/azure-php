<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Feature;

use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Models\AcquireBlobLeaseOptions;
use AzureOss\Storage\Blob\Models\BlobRequestConditions;
use AzureOss\Storage\Blob\Models\BreakBlobLeaseOptions;
use AzureOss\Storage\Blob\Models\ReleaseBlobLeaseOptions;
use AzureOss\Storage\Common\Models\ETag;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Server\Server;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobLeaseClientTest extends TestCase
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
    public function blob_lease_client_acquires_renews_changes_releases_and_breaks_leases(): void
    {
        Server::enqueue([
            new Response(201, [
                'x-ms-lease-id' => '11111111-1111-4111-8111-111111111111',
                'ETag' => '"lease-etag"',
                'Last-Modified' => 'Wed, 01 Jan 2025 12:34:56 GMT',
            ]),
            new Response(200, [
                'x-ms-lease-id' => '11111111-1111-4111-8111-111111111111',
            ]),
            new Response(200, [
                'x-ms-lease-id' => '22222222-2222-4222-8222-222222222222',
            ]),
            new Response(200),
            new Response(202, [
                'x-ms-lease-time' => '5',
            ]),
        ]);

        $leaseClient = $this->blob->getBlobLeaseClient('11111111-1111-4111-8111-111111111111');

        $lease = $leaseClient->acquire(60, new AcquireBlobLeaseOptions(
            conditions: new BlobRequestConditions(ifMatch: new ETag('"lease-etag"')),
        ));
        $leaseClient->renew();
        $leaseClient->change('22222222-2222-4222-8222-222222222222');
        $leaseClient->release(new ReleaseBlobLeaseOptions(
            conditions: new BlobRequestConditions(ifUnmodifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC')),
        ));
        $brokenLease = $leaseClient->break(new BreakBlobLeaseOptions(breakPeriodSeconds: 5));

        $requests = Server::received();

        self::assertSame('11111111-1111-4111-8111-111111111111', $lease->leaseId);
        self::assertSame('"lease-etag"', (string) $lease->eTag);
        self::assertSame(5, $brokenLease->leaseTime);
        self::assertCount(5, $requests);

        self::assertLeaseQuery('', $requests[0]->getUri()->getQuery());
        self::assertSame('acquire', $requests[0]->getHeaderLine('x-ms-lease-action'));
        self::assertSame('60', $requests[0]->getHeaderLine('x-ms-lease-duration'));
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[0]->getHeaderLine('x-ms-proposed-lease-id'));
        self::assertSame('"lease-etag"', $requests[0]->getHeaderLine('If-Match'));

        self::assertSame('renew', $requests[1]->getHeaderLine('x-ms-lease-action'));
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[1]->getHeaderLine('x-ms-lease-id'));

        self::assertSame('change', $requests[2]->getHeaderLine('x-ms-lease-action'));
        self::assertSame('11111111-1111-4111-8111-111111111111', $requests[2]->getHeaderLine('x-ms-lease-id'));
        self::assertSame('22222222-2222-4222-8222-222222222222', $requests[2]->getHeaderLine('x-ms-proposed-lease-id'));

        self::assertSame('release', $requests[3]->getHeaderLine('x-ms-lease-action'));
        self::assertSame('22222222-2222-4222-8222-222222222222', $requests[3]->getHeaderLine('x-ms-lease-id'));
        self::assertSame('Wed, 01 Jan 2025 12:34:56 GMT', $requests[3]->getHeaderLine('If-Unmodified-Since'));

        self::assertSame('break', $requests[4]->getHeaderLine('x-ms-lease-action'));
        self::assertSame('5', $requests[4]->getHeaderLine('x-ms-lease-break-period'));
    }

    #[Test]
    public function container_lease_client_sends_container_lease_query(): void
    {
        Server::enqueue([
            new Response(201, [
                'x-ms-lease-id' => '11111111-1111-4111-8111-111111111111',
            ]),
        ]);

        $this->container
            ->getBlobLeaseClient('11111111-1111-4111-8111-111111111111')
            ->acquire();

        $requests = Server::received();

        self::assertCount(1, $requests);
        self::assertLeaseQuery('container', $requests[0]->getUri()->getQuery());
        self::assertSame('-1', $requests[0]->getHeaderLine('x-ms-lease-duration'));
    }

    private static function assertLeaseQuery(string $restype, string $queryString): void
    {
        parse_str($queryString, $query);

        self::assertSame('lease', $query['comp'] ?? null);
        if ($restype === '') {
            self::assertArrayNotHasKey('restype', $query);

            return;
        }

        self::assertSame($restype, $query['restype'] ?? null);
    }
}
