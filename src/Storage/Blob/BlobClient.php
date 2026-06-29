<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob;

use AzureOss\Identity\TokenCredential;
use AzureOss\Storage\Blob\Exceptions\BlobStorageException;
use AzureOss\Storage\Blob\Exceptions\BlobStorageExceptionDeserializer;
use AzureOss\Storage\Blob\Exceptions\InvalidBlobUriException;
use AzureOss\Storage\Blob\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\Blob\Helpers\BlobUriBuilderHelper;
use AzureOss\Storage\Blob\Helpers\BlobUriParserHelper;
use AzureOss\Storage\Blob\Helpers\MetadataHelper;
use AzureOss\Storage\Blob\Helpers\StreamHelper;
use AzureOss\Storage\Blob\Models\AbortCopyFromUriOptions;
use AzureOss\Storage\Blob\Models\BlobClientOptions;
use AzureOss\Storage\Blob\Models\BlobCopyResult;
use AzureOss\Storage\Blob\Models\BlobDownloadStreamingResult;
use AzureOss\Storage\Blob\Models\BlobErrorCode;
use AzureOss\Storage\Blob\Models\BlobHttpHeaders;
use AzureOss\Storage\Blob\Models\BlobLeaseClientOptions;
use AzureOss\Storage\Blob\Models\BlobProperties;
use AzureOss\Storage\Blob\Models\BlobRequestConditions;
use AzureOss\Storage\Blob\Models\BlobSnapshotInfo;
use AzureOss\Storage\Blob\Models\BlockBlobClientOptions;
use AzureOss\Storage\Blob\Models\CommitBlockListOptions;
use AzureOss\Storage\Blob\Models\CopyStatus;
use AzureOss\Storage\Blob\Models\CreateSnapshotOptions;
use AzureOss\Storage\Blob\Models\DeleteBlobOptions;
use AzureOss\Storage\Blob\Models\DownloadBlobOptions;
use AzureOss\Storage\Blob\Models\GetBlobPropertiesOptions;
use AzureOss\Storage\Blob\Models\GetBlobTagsOptions;
use AzureOss\Storage\Blob\Models\RequestConditionSet;
use AzureOss\Storage\Blob\Models\SetBlobHttpHeadersOptions;
use AzureOss\Storage\Blob\Models\SetBlobMetadataOptions;
use AzureOss\Storage\Blob\Models\SetBlobTagsOptions;
use AzureOss\Storage\Blob\Models\StageBlockOptions;
use AzureOss\Storage\Blob\Models\StartCopyFromUriOptions;
use AzureOss\Storage\Blob\Models\SyncCopyFromUriOptions;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Blob\Requests\BlobTagsBody;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use AzureOss\Storage\Blob\Specialized\BlobLeaseClient;
use AzureOss\Storage\Blob\Specialized\BlockBlobClient;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Helpers\StorageUriParserHelper;
use AzureOss\Storage\Common\Middleware\ClientFactory;
use AzureOss\Storage\Common\Sas\SasProtocol;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Utils as StreamUtils;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Provides operations for an Azure Storage blob.
 *
 * Supports streaming downloads, automatic single-request or staged-block uploads,
 * metadata and tag management, copy operations, leases, and SAS generation.
 */
final class BlobClient
{
    private readonly Client $client;

    private readonly BlockBlobClient $blockBlobClient;

    public readonly string $containerName;

    public readonly string $blobName;

    /**
     * @param  UriInterface  $uri  URI of the blob, including any SAS query string.
     * @param  StorageSharedKeyCredential|TokenCredential|null  $credential  Credential used to authorize requests, or null for anonymous/SAS access.
     * @param  BlobClientOptions  $options  Client transport and service-version options.
     *
     * @throws InvalidBlobUriException When the URI does not identify both a container and blob.
     */
    public function __construct(
        public readonly UriInterface $uri,
        public readonly StorageSharedKeyCredential|TokenCredential|null $credential = null,
        private readonly BlobClientOptions $options = new BlobClientOptions,
    ) {
        $this->containerName = BlobUriParserHelper::getContainerName($uri);
        $this->blobName = BlobUriParserHelper::getBlobName($uri);
        $this->client = (new ClientFactory)->create($uri, $credential, new BlobStorageExceptionDeserializer, $options->httpClientOptions, $options->apiVersion);
        $this->blockBlobClient = new BlockBlobClient($uri, $credential, new BlockBlobClientOptions($options->httpClientOptions, $options->apiVersion));
    }

    /**
     * Creates a client that targets the specified snapshot without making a service request.
     *
     * Pass null or an empty string to remove the snapshot selector.
     */
    public function withSnapshot(?string $snapshot): self
    {
        return new self(
            BlobUriBuilderHelper::withSnapshot($this->uri, $snapshot),
            $this->credential,
            $this->options,
        );
    }

    /**
     * Creates a client that targets the specified blob version without making a service request.
     *
     * Pass null or an empty string to remove the version selector.
     */
    public function withVersion(?string $versionId): self
    {
        return new self(
            BlobUriBuilderHelper::withVersion($this->uri, $versionId),
            $this->credential,
            $this->options,
        );
    }

    /** Creates a read-only snapshot of the base blob. */
    public function createSnapshot(CreateSnapshotOptions $options = new CreateSnapshotOptions): BlobSnapshotInfo
    {
        /** @phpstan-ignore-next-line */
        return $this->createSnapshotAsync($options)->wait();
    }

    /** Asynchronously creates a read-only snapshot of the base blob. */
    public function createSnapshotAsync(CreateSnapshotOptions $options = new CreateSnapshotOptions): PromiseInterface
    {
        return $this->client
            ->putAsync($this->uri, [
                RequestOptions::QUERY => ['comp' => 'snapshot'],
                RequestOptions::HEADERS => [
                    ...MetadataHelper::metadataToHeaders($options->metadata),
                    ...($options->conditions?->toHeaders('BlobClient::createSnapshot', RequestConditionSet::ALL) ?? []),
                ],
            ])
            ->then(BlobSnapshotInfo::fromResponse(...));
    }

    /** Downloads the blob as a streaming response. */
    public function downloadStreaming(DownloadBlobOptions $options = new DownloadBlobOptions): BlobDownloadStreamingResult
    {
        /** @phpstan-ignore-next-line */
        return $this->downloadStreamingAsync($options)->wait();
    }

    /** Asynchronously downloads the blob as a streaming response. */
    public function downloadStreamingAsync(DownloadBlobOptions $options = new DownloadBlobOptions): PromiseInterface
    {
        return $this->client
            ->getAsync($this->uri, [
                RequestOptions::STREAM => true,
                RequestOptions::HEADERS => $options->conditions?->toHeaders('BlobClient::downloadStreaming', RequestConditionSet::ALL) ?? [],
            ])
            ->then(BlobDownloadStreamingResult::fromResponse(...));
    }

    /** Gets the blob's properties and metadata without downloading its content. */
    public function getProperties(GetBlobPropertiesOptions $options = new GetBlobPropertiesOptions): BlobProperties
    {
        /** @phpstan-ignore-next-line */
        return $this->getPropertiesAsync($options)->wait();
    }

    /** Asynchronously gets the blob's properties and metadata. */
    public function getPropertiesAsync(GetBlobPropertiesOptions $options = new GetBlobPropertiesOptions): PromiseInterface
    {
        return $this->client
            ->headAsync($this->uri, [
                RequestOptions::HEADERS => $options->conditions?->toHeaders('BlobClient::getProperties', RequestConditionSet::ALL) ?? [],
            ])
            ->then(BlobProperties::fromResponseHeaders(...));
    }

    /** Creates a lease client for this blob without making a service request. */
    public function getBlobLeaseClient(?string $leaseId = null): BlobLeaseClient
    {
        return new BlobLeaseClient(
            $this->uri,
            $this->credential,
            $leaseId,
            options: new BlobLeaseClientOptions($this->options->httpClientOptions, $this->options->apiVersion),
        );
    }

    /**
     * Replaces all user-defined metadata on the blob.
     *
     * @param  array<string, string>  $metadata
     */
    public function setMetadata(array $metadata, SetBlobMetadataOptions $options = new SetBlobMetadataOptions): void
    {
        $this->setMetadataAsync($metadata, $options)->wait();
    }

    /**
     * Asynchronously replaces all user-defined metadata on the blob.
     *
     * @param  array<string, string>  $metadata
     */
    public function setMetadataAsync(array $metadata, SetBlobMetadataOptions $options = new SetBlobMetadataOptions): PromiseInterface
    {
        return $this->client
            ->putAsync($this->uri, [
                RequestOptions::QUERY => [
                    'comp' => 'metadata',
                ],
                RequestOptions::HEADERS => [
                    ...MetadataHelper::metadataToHeaders($metadata),
                    ...($options->conditions?->toHeaders('BlobClient::setMetadata', RequestConditionSet::ALL) ?? []),
                ],
            ]);
    }

    /**
     * Sets the blob's standard HTTP headers.
     */
    public function setHttpHeaders(BlobHttpHeaders $httpHeaders, SetBlobHttpHeadersOptions $options = new SetBlobHttpHeadersOptions): void
    {
        $this->setHttpHeadersAsync($httpHeaders, $options)->wait();
    }

    /**
     * Asynchronously sets the blob's standard HTTP headers.
     */
    public function setHttpHeadersAsync(BlobHttpHeaders $httpHeaders, SetBlobHttpHeadersOptions $options = new SetBlobHttpHeadersOptions): PromiseInterface
    {
        return $this->client->putAsync($this->uri, [
            RequestOptions::QUERY => ['comp' => 'properties'],
            RequestOptions::HEADERS => [
                ...$httpHeaders->toArray(),
                ...($options->conditions?->toHeaders('BlobClient::setHttpHeaders', RequestConditionSet::ALL) ?? []),
            ],
        ]);
    }

    /** Deletes the blob. */
    public function delete(DeleteBlobOptions $options = new DeleteBlobOptions): void
    {
        $this->deleteAsync($options)->wait();
    }

    /** Asynchronously deletes the blob. */
    public function deleteAsync(DeleteBlobOptions $options = new DeleteBlobOptions): PromiseInterface
    {
        return $this->client->deleteAsync($this->uri, [
            RequestOptions::HEADERS => [
                ...($options->conditions?->toHeaders('BlobClient::delete', RequestConditionSet::ALL) ?? []),
                ...($options->snapshotsOption === null ? [] : ['x-ms-delete-snapshots' => $options->snapshotsOption->value]),
            ],
        ]);
    }

    /**
     * Restores a soft-deleted blob and all associated soft-deleted snapshots or versions.
     *
     * Calling this operation for an active blob succeeds without changing it. When blob
     * versioning is enabled, restoring a previous version as the current blob still
     * requires copying that version over the base blob.
     */
    public function undelete(): void
    {
        $this->undeleteAsync()->wait();
    }

    /** Asynchronously restores a soft-deleted blob and its deleted snapshots or versions. */
    public function undeleteAsync(): PromiseInterface
    {
        return $this->client->putAsync($this->uri, [
            RequestOptions::QUERY => ['comp' => 'undelete'],
        ]);
    }

    /** Deletes the blob if it exists. */
    public function deleteIfExists(DeleteBlobOptions $options = new DeleteBlobOptions): void
    {
        $this->deleteIfExistsAsync($options)->wait();
    }

    /** Asynchronously deletes the blob if it exists. */
    public function deleteIfExistsAsync(DeleteBlobOptions $options = new DeleteBlobOptions): PromiseInterface
    {
        return $this->deleteAsync($options)->otherwise(
            function (\Throwable $e) {
                if ($e instanceof BlobStorageException && $e->errorCode === BlobErrorCode::BlobNotFound) {
                    return null;
                }

                throw $e;
            },
        );
    }

    /** Determines whether the blob exists. */
    public function exists(): bool
    {
        /** @phpstan-ignore-next-line */
        return $this->existsAsync()->wait();
    }

    /** Asynchronously determines whether the blob exists. */
    public function existsAsync(): PromiseInterface
    {
        return $this->getPropertiesAsync()
            ->then(fn () => true)
            ->otherwise(
                function (\Throwable $e) {
                    if ($e instanceof BlobStorageException && $e->errorCode === BlobErrorCode::BlobNotFound) {
                        return false;
                    }

                    throw $e;
                },
            );
    }

    /**
     * Uploads content, staging blocks automatically when it exceeds the configured threshold.
     *
     * @param  string|resource|StreamInterface  $content
     */
    public function upload($content, UploadBlobOptions $options = new UploadBlobOptions): void
    {
        $this->uploadAsync($content, $options)->wait();
    }

    /**
     * Asynchronously uploads content, staging blocks automatically when required.
     *
     * @param  string|resource|StreamInterface  $content
     */
    public function uploadAsync($content, UploadBlobOptions $options = new UploadBlobOptions): PromiseInterface
    {
        $content = StreamHelper::createUploadStream($content, $options->maximumTransferSize ?? 8_000_000);
        $maximumTransferSize = $this->resolveMaximumTransferSize($content, $options);

        if ($content->getSize() === null || $content->getSize() > $options->initialTransferSize) {
            return $this->uploadViaBlockBlobAsync(
                $content,
                $options->maximumConcurrency,
                $maximumTransferSize,
                $options->httpHeaders,
                $options->conditions,
            );
        } else {
            return $this->uploadViaPutBlobAsync($content, $options->httpHeaders, $options->conditions);
        }
    }

    private function uploadViaPutBlobAsync(StreamInterface $content, BlobHttpHeaders $httpHeaders, ?BlobRequestConditions $conditions): PromiseInterface
    {
        return $this->client
            ->putAsync($this->uri, [
                RequestOptions::HEADERS => array_filter([
                    'x-ms-blob-type' => 'BlockBlob',
                    'Content-Length' => $content->getSize() === null ? null : (string) $content->getSize(),
                    ...$httpHeaders->toArray(),
                    ...($conditions?->toHeaders('BlobClient::upload', RequestConditionSet::ALL) ?? []),
                ], fn ($value) => $value !== null),
                RequestOptions::BODY => $content,
            ]);
    }

    private function uploadViaBlockBlobAsync(StreamInterface $content, int $maximumConcurrency, int $maximumTransferSize, BlobHttpHeaders $httpHeaders, ?BlobRequestConditions $conditions): PromiseInterface
    {
        $blockIds = [];
        $contextMD5 = $httpHeaders->contentHash === '' ? hash_init('md5') : null;

        $stageBlockConditions = $conditions?->leaseId === null
            ? null
            : new BlobRequestConditions(leaseId: $conditions->leaseId);

        $putBlockRequestGenerator = function () use (&$content, &$blockIds, &$contextMD5, $maximumTransferSize, $stageBlockConditions): \Generator {
            while (true) {
                $blockContent = StreamUtils::streamFor();
                $remaining = $maximumTransferSize;

                while ($remaining > 0 && ! $content->eof()) {
                    $chunk = $content->read(min(8192, $remaining));
                    if ($chunk === '') {
                        break;
                    }

                    $remaining -= strlen($chunk);
                    $blockContent->write($chunk);

                    if ($contextMD5 !== null) {
                        hash_update($contextMD5, $chunk);
                    }
                }

                if ($blockContent->getSize() === 0) {
                    break;
                }

                if ($blockContent->isSeekable()) {
                    $blockContent->rewind();
                }

                $blockId = $this->getNextBlockId($blockIds);
                $blockIds[] = $blockId;

                yield fn () => $this->blockBlobClient->stageBlockAsync(
                    $blockId,
                    $blockContent,
                    new StageBlockOptions(conditions: $stageBlockConditions),
                );
            }
        };

        $pool = new Pool(
            $this->client,
            $putBlockRequestGenerator(),
            ['concurrency' => $maximumConcurrency],
        );

        return $pool
            ->promise()
            ->then(
                function () use (&$blockIds, $httpHeaders, &$contextMD5, $conditions) {
                    $commitHeaders = clone $httpHeaders;

                    if ($contextMD5 !== null && $commitHeaders->contentHash === '') {
                        $commitHeaders->contentHash = hash_final($contextMD5, true);
                    }

                    return $this->blockBlobClient->commitBlockListAsync(
                        $blockIds,
                        new CommitBlockListOptions(
                            httpHeaders: $commitHeaders,
                            conditions: $conditions,
                        ),
                    );
                },
            );
    }

    private function resolveMaximumTransferSize(StreamInterface $content, UploadBlobOptions $options): int
    {
        if ($options->maximumTransferSize !== null) {
            return $options->maximumTransferSize;
        }

        $contentLength = $content->getSize();

        if ($contentLength === null) {
            return 8_000_000;
        }

        return max(8_000_000, (int) ceil($contentLength / 50_000));
    }

    /**
     * @param  string[]  $blockIds
     */
    private function getNextBlockId(array $blockIds): string
    {
        return base64_encode(str_pad((string) count($blockIds), 6, '0', STR_PAD_LEFT));
    }

    /** Copies a source blob to this blob in a synchronous service operation. */
    public function syncCopyFromUri(UriInterface $source, SyncCopyFromUriOptions $options = new SyncCopyFromUriOptions): BlobCopyResult
    {
        /** @phpstan-ignore-next-line */
        return $this->syncCopyFromUriAsync($source, $options)->wait();
    }

    /** Asynchronously performs a synchronous server-side copy to this blob. */
    public function syncCopyFromUriAsync(UriInterface $source, SyncCopyFromUriOptions $options = new SyncCopyFromUriOptions): PromiseInterface
    {
        return $this->client
            ->putAsync($this->uri, [
                'headers' => [
                    'x-ms-copy-source' => (string) $source,
                    'x-ms-requires-sync' => 'true',
                    ...($options->destinationConditions?->toHeaders(
                        'BlobClient::syncCopyFromUri destination',
                        RequestConditionSet::ALL,
                    ) ?? []),
                    ...($options->sourceConditions?->toHeaders(
                        'BlobClient::syncCopyFromUri source',
                        RequestConditionSet::HTTP_ONLY,
                        prefix: 'x-ms-source-',
                    ) ?? []),
                ],
            ])
            ->then(BlobCopyResult::fromResponse(...));
    }

    /** Starts a potentially long-running server-side copy to this blob. */
    public function startCopyFromUri(UriInterface $source, StartCopyFromUriOptions $options = new StartCopyFromUriOptions): BlobCopyResult
    {
        /** @phpstan-ignore-next-line */
        return $this->startCopyFromUriAsync($source, $options)->wait();
    }

    /** Asynchronously starts a potentially long-running server-side copy. */
    public function startCopyFromUriAsync(UriInterface $source, StartCopyFromUriOptions $options = new StartCopyFromUriOptions): PromiseInterface
    {
        return $this->client
            ->putAsync($this->uri, [
                RequestOptions::HEADERS => [
                    'x-ms-copy-source' => (string) $source,
                    ...($options->destinationConditions?->toHeaders(
                        'BlobClient::startCopyFromUri destination',
                        RequestConditionSet::ALL,
                    ) ?? []),
                    ...($options->sourceConditions?->toHeaders(
                        'BlobClient::startCopyFromUri source',
                        RequestConditionSet::HTTP_ONLY,
                        prefix: 'x-ms-source-',
                    ) ?? []),
                ],
            ])
            ->then(BlobCopyResult::fromResponse(...));
    }

    /** Aborts a pending copy operation identified by its copy ID. */
    public function abortCopyFromUri(string $copyId, AbortCopyFromUriOptions $options = new AbortCopyFromUriOptions): void
    {
        $this->abortCopyFromUriAsync($copyId, $options)->wait();
    }

    /** Asynchronously aborts a pending copy operation. */
    public function abortCopyFromUriAsync(string $copyId, AbortCopyFromUriOptions $options = new AbortCopyFromUriOptions): PromiseInterface
    {
        return $this->client
            ->putAsync($this->uri, [
                'query' => [
                    'comp' => 'copy',
                    'copyid' => $copyId,
                ],
                'headers' => [
                    'x-ms-copy-action' => 'abort',
                    ...($options->conditions?->toHeaders(
                        'BlobClient::abortCopyFromUri',
                        RequestConditionSet::LEASE_ONLY,
                    ) ?? []),
                ],
            ]);
    }

    /**
     * Waits for a pending copy operation to complete by polling the blob properties.
     *
     * This method polls the blob's properties at regular intervals until the copy operation
     * reaches a terminal state (success, failed, or aborted).
     *
     * @param  int  $pollingIntervalMs  Interval between polling attempts in milliseconds (default: 1000ms)
     * @param  int|null  $timeoutMs  Maximum time to wait in milliseconds (default: null for no timeout)
     * @return BlobProperties The final blob properties after copy completion
     *
     * @throws \RuntimeException If copy operation fails or is aborted
     * @throws \RuntimeException If timeout is reached before copy completes
     */
    /**
     * Polls this blob until its current copy operation completes or the timeout expires.
     *
     * @param  int  $pollingIntervalMs  Delay between property requests in milliseconds.
     * @param  int|null  $timeoutMs  Maximum wait in milliseconds, or null for no timeout.
     */
    public function waitForCopyCompletion(int $pollingIntervalMs = 1000, ?int $timeoutMs = null): BlobProperties
    {
        $startTime = microtime(true);

        while (true) {
            $properties = $this->getProperties();

            // If there's no copy operation in progress, return immediately
            if ($properties->copyStatus === null) {
                return $properties;
            }

            // Check if copy reached a terminal state
            switch ($properties->copyStatus) {
                case CopyStatus::SUCCESS:
                    return $properties;

                case CopyStatus::FAILED:
                    throw new \RuntimeException(
                        'Blob copy operation failed'.
                        ($properties->copyStatusDescription !== null ? ": {$properties->copyStatusDescription}" : ''),
                    );

                case CopyStatus::ABORTED:
                    throw new \RuntimeException('Blob copy operation was aborted');
                case CopyStatus::PENDING:
                    // Check timeout
                    if ($timeoutMs !== null) {
                        $elapsedMs = (microtime(true) - $startTime) * 1000;
                        if ($elapsedMs >= $timeoutMs) {
                            throw new \RuntimeException(
                                sprintf(
                                    'Timeout waiting for blob copy to complete after %d ms',
                                    $timeoutMs,
                                ),
                            );
                        }
                    }

                    // Wait before next poll
                    usleep($pollingIntervalMs * 1000);
                    break;
            }
        }
    }

    /** Returns whether this client has a shared-key credential capable of signing a blob SAS. */
    public function canGenerateSasUri(): bool
    {
        return $this->credential instanceof StorageSharedKeyCredential;
    }

    /**
     * Generates a URI for this blob containing a signed service SAS query string.
     *
     * @throws UnableToGenerateSasException When the client does not have a shared-key credential.
     */
    public function generateSasUri(BlobSasBuilder $blobSasBuilder): UriInterface
    {
        if (! $this->credential instanceof StorageSharedKeyCredential) {
            throw new UnableToGenerateSasException;
        }

        $builder = clone $blobSasBuilder;

        if (StorageUriParserHelper::isDevelopmentUri($this->uri)) {
            $builder->setProtocol(SasProtocol::HTTPS_AND_HTTP);
        }

        $existingQuery = Query::parse($this->uri->getQuery());
        $snapshot = $existingQuery['snapshot'] ?? null;
        $versionId = $existingQuery['versionid'] ?? null;

        $sas = $builder
            ->setContainerName($this->containerName)
            ->setBlobName($this->blobName)
            ->setSnapshot(is_string($snapshot) ? $snapshot : null)
            ->setBlobVersionId(is_string($versionId) ? $versionId : null)
            ->build($this->credential);

        return $this->uri->withQuery(Query::build([
            ...$existingQuery,
            ...Query::parse($sas),
        ]));
    }

    /**
     * Replaces all index tags on the blob.
     *
     * @param  array<string>  $tags
     */
    public function setTags(array $tags, SetBlobTagsOptions $options = new SetBlobTagsOptions): void
    {
        $this->setTagsAsync($tags, $options)->wait();
    }

    /**
     * Asynchronously replaces all index tags on the blob.
     *
     * @param  array<string>  $tags
     */
    public function setTagsAsync(array $tags, SetBlobTagsOptions $options = new SetBlobTagsOptions): PromiseInterface
    {
        return $this->client
            ->putAsync($this->uri, [
                RequestOptions::QUERY => [
                    'comp' => 'tags',
                ],
                RequestOptions::HEADERS => $options->conditions?->toHeaders('BlobClient::setTags', RequestConditionSet::ALL) ?? [],
                RequestOptions::BODY => (new BlobTagsBody($tags))->toXml()->asXML(),
            ]);
    }

    /**
     * Gets all index tags associated with the blob.
     *
     * @return array<string>
     */
    public function getTags(GetBlobTagsOptions $options = new GetBlobTagsOptions): array
    {
        /** @phpstan-ignore-next-line */
        return $this->getTagsAsync($options)->wait();
    }

    /** Asynchronously gets all index tags associated with the blob. */
    public function getTagsAsync(GetBlobTagsOptions $options = new GetBlobTagsOptions): PromiseInterface
    {
        return $this->client
            ->getAsync($this->uri, [
                RequestOptions::QUERY => [
                    'comp' => 'tags',
                ],
                RequestOptions::HEADERS => $options->conditions?->toHeaders('BlobClient::getTags', RequestConditionSet::ALL) ?? [],
            ])
            ->then(
                fn (ResponseInterface $response) => BlobTagsBody::fromXml(new \SimpleXMLElement($response->getBody()->getContents()))->tags,
            );
    }
}
