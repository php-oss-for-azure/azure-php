<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Specialized;

use AzureOss\Identity\TokenCredential;
use AzureOss\Storage\Blob\Exceptions\BlobStorageExceptionDeserializer;
use AzureOss\Storage\Blob\Models\AcquireBlobLeaseOptions;
use AzureOss\Storage\Blob\Models\BlobLease;
use AzureOss\Storage\Blob\Models\BlobLeaseClientOptions;
use AzureOss\Storage\Blob\Models\BlobRequestConditions;
use AzureOss\Storage\Blob\Models\BreakBlobLeaseOptions;
use AzureOss\Storage\Blob\Models\ChangeBlobLeaseOptions;
use AzureOss\Storage\Blob\Models\ReleaseBlobLeaseOptions;
use AzureOss\Storage\Blob\Models\ReleasedObjectInfo;
use AzureOss\Storage\Blob\Models\RenewBlobLeaseOptions;
use AzureOss\Storage\Blob\Models\RequestConditionSet;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Middleware\ClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

final class BlobLeaseClient
{
    public const INFINITE_LEASE_DURATION = -1;

    private readonly Client $client;

    public function __construct(
        public readonly UriInterface $uri,
        public readonly StorageSharedKeyCredential|TokenCredential|null $credential = null,
        public ?string $leaseId = null,
        private readonly bool $container = false,
        private readonly BlobLeaseClientOptions $options = new BlobLeaseClientOptions,
    ) {
        $this->leaseId ??= self::createLeaseId();
        $this->client = (new ClientFactory)->create(
            $uri,
            $credential,
            new BlobStorageExceptionDeserializer,
            $this->options->httpClientOptions,
            $this->options->apiVersion,
        );
    }

    public function acquire(int $durationSeconds = self::INFINITE_LEASE_DURATION, AcquireBlobLeaseOptions $options = new AcquireBlobLeaseOptions): BlobLease
    {
        /** @phpstan-ignore-next-line */
        return $this->acquireAsync($durationSeconds, $options)->wait();
    }

    public function acquireAsync(int $durationSeconds = self::INFINITE_LEASE_DURATION, AcquireBlobLeaseOptions $options = new AcquireBlobLeaseOptions): PromiseInterface
    {
        $conditionHeaders = $this->conditionHeaders($options->conditions, 'BlobLeaseClient::acquire');

        return $this->client->putAsync($this->uri, [
            RequestOptions::QUERY => array_filter([
                'comp' => 'lease',
                'restype' => $this->container ? 'container' : null,
            ]),
            RequestOptions::HEADERS => [
                'x-ms-lease-action' => 'acquire',
                'x-ms-lease-duration' => (string) $durationSeconds,
                'x-ms-proposed-lease-id' => $this->leaseId,
                ...$conditionHeaders,
            ],
        ])->then($this->updateLeaseIdFromResponse(...));
    }

    public function renew(RenewBlobLeaseOptions $options = new RenewBlobLeaseOptions): BlobLease
    {
        /** @phpstan-ignore-next-line */
        return $this->renewAsync($options)->wait();
    }

    public function renewAsync(RenewBlobLeaseOptions $options = new RenewBlobLeaseOptions): PromiseInterface
    {
        $conditionHeaders = $this->conditionHeaders($options->conditions, 'BlobLeaseClient::renew');

        return $this->client->putAsync($this->uri, [
            RequestOptions::QUERY => array_filter([
                'comp' => 'lease',
                'restype' => $this->container ? 'container' : null,
            ]),
            RequestOptions::HEADERS => [
                ...$conditionHeaders,
                'x-ms-lease-action' => 'renew',
                'x-ms-lease-id' => $this->leaseId,
            ],
        ])->then($this->updateLeaseIdFromResponse(...));
    }

    public function change(string $proposedLeaseId, ChangeBlobLeaseOptions $options = new ChangeBlobLeaseOptions): BlobLease
    {
        /** @phpstan-ignore-next-line */
        return $this->changeAsync($proposedLeaseId, $options)->wait();
    }

    public function changeAsync(string $proposedLeaseId, ChangeBlobLeaseOptions $options = new ChangeBlobLeaseOptions): PromiseInterface
    {
        $conditionHeaders = $this->conditionHeaders($options->conditions, 'BlobLeaseClient::change');

        return $this->client->putAsync($this->uri, [
            RequestOptions::QUERY => array_filter([
                'comp' => 'lease',
                'restype' => $this->container ? 'container' : null,
            ]),
            RequestOptions::HEADERS => [
                ...$conditionHeaders,
                'x-ms-lease-action' => 'change',
                'x-ms-lease-id' => $this->leaseId,
                'x-ms-proposed-lease-id' => $proposedLeaseId,
            ],
        ])->then(function (ResponseInterface $response) use ($proposedLeaseId): BlobLease {
            $this->leaseId = $response->hasHeader('x-ms-lease-id')
                ? $response->getHeaderLine('x-ms-lease-id')
                : $proposedLeaseId;

            return BlobLease::fromResponse($response, $this->leaseId);
        });
    }

    public function release(ReleaseBlobLeaseOptions $options = new ReleaseBlobLeaseOptions): ReleasedObjectInfo
    {
        /** @phpstan-ignore-next-line */
        return $this->releaseAsync($options)->wait();
    }

    public function releaseAsync(ReleaseBlobLeaseOptions $options = new ReleaseBlobLeaseOptions): PromiseInterface
    {
        $conditionHeaders = $this->conditionHeaders($options->conditions, 'BlobLeaseClient::release');

        return $this->client->putAsync($this->uri, [
            RequestOptions::QUERY => array_filter([
                'comp' => 'lease',
                'restype' => $this->container ? 'container' : null,
            ]),
            RequestOptions::HEADERS => [
                ...$conditionHeaders,
                'x-ms-lease-action' => 'release',
                'x-ms-lease-id' => $this->leaseId,
            ],
        ])->then(ReleasedObjectInfo::fromResponse(...));
    }

    public function break(?int $breakPeriodSeconds = null, BreakBlobLeaseOptions $options = new BreakBlobLeaseOptions): BlobLease
    {
        /** @phpstan-ignore-next-line */
        return $this->breakAsync($breakPeriodSeconds, $options)->wait();
    }

    public function breakAsync(?int $breakPeriodSeconds = null, BreakBlobLeaseOptions $options = new BreakBlobLeaseOptions): PromiseInterface
    {
        $conditionHeaders = $this->conditionHeaders($options->conditions, 'BlobLeaseClient::break');

        return $this->client->putAsync($this->uri, [
            RequestOptions::QUERY => array_filter([
                'comp' => 'lease',
                'restype' => $this->container ? 'container' : null,
            ]),
            RequestOptions::HEADERS => array_filter([
                ...$conditionHeaders,
                'x-ms-lease-action' => 'break',
                'x-ms-lease-break-period' => $breakPeriodSeconds,
            ], fn ($value) => $value !== null),
        ])->then(fn (ResponseInterface $response): BlobLease => BlobLease::fromResponse($response));
    }

    private function updateLeaseIdFromResponse(ResponseInterface $response): BlobLease
    {
        $lease = BlobLease::fromResponse($response, $this->leaseId);
        $this->leaseId = $lease->leaseId;

        return $lease;
    }

    /**
     * @return array<string, string>
     */
    private function conditionHeaders(?BlobRequestConditions $conditions, string $operation): array
    {
        return $conditions?->toHeaders(
            $operation,
            $this->container ? RequestConditionSet::DATES_ONLY : RequestConditionSet::HTTP_ONLY,
        ) ?? [];
    }

    private static function createLeaseId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }
}
