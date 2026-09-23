<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Specialized;

use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\Models\DeleteBlobOptions;
use AzureOss\Storage\Blob\Models\RequestConditionSet;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Collects blob operations to submit in a single batch request.
 *
 * Create a batch with `BlobBatchClient::createBatch()`, add up to `BlobBatchClient::MAX_BATCH_SIZE` operations,
 * and submit it once with `BlobBatchClient::submitBatch()` or `submitBatchAsync()` through a batch client
 * with the same URI and credential. Adding operations makes no service request.
 */
final class BlobBatch implements \Countable
{
    /** @internal */
    public const BLOB_SELECTORS = ['snapshot', 'versionid'];

    /** @var list<RequestInterface> */
    private array $subRequests = [];

    /** @var list<BlobClient|string> */
    private array $blobs = [];

    private bool $submitted = false;

    /**
     * @param  BlobBatchClient  $client  Batch client whose URI, scope and credential the operations use.
     */
    public function __construct(
        private readonly BlobBatchClient $client,
    ) {}

    /**
     * Adds a blob delete to the batch.
     *
     * @param  BlobClient|string  $blob  A blob client in the batch client's scope, which may target a snapshot or version,
     *                                   or a blob name relative to the batch client's URI: `<blob>` for a container
     *                                   batch client, `<container>/<blob>` for an account batch client.
     * @param  DeleteBlobOptions  $options  Conditions and snapshot handling for this blob only.
     *
     * @throws \InvalidArgumentException When the name is empty or lacks a container, the blob client is outside the batch
     *                                   client's scope, or the batch already holds `BlobBatchClient::MAX_BATCH_SIZE` operations.
     * @throws \LogicException When the batch has already been submitted.
     */
    public function deleteBlob(BlobClient|string $blob, DeleteBlobOptions $options = new DeleteBlobOptions): void
    {
        $this->add($blob, new Request('DELETE', $this->getBlobUri($blob), [
            ...($options->conditions?->toHeaders('BlobBatch::deleteBlob', RequestConditionSet::ALL) ?? []),
            ...($options->snapshotsOption === null ? [] : ['x-ms-delete-snapshots' => $options->snapshotsOption->value]),
        ]));
    }

    /** Returns the number of operations in the batch. */
    public function count(): int
    {
        return count($this->subRequests);
    }

    /**
     * @return non-empty-list<RequestInterface>
     *
     * @internal
     */
    public function submitTo(BlobBatchClient $client): array
    {
        if ($this->submitted) {
            throw new \InvalidArgumentException('The batch has already been submitted.');
        }

        if ((string) $this->client->uri !== (string) $client->uri
            || $this->client->credential !== $client->credential
            || $this->client->containerName !== $client->containerName
        ) {
            throw new \InvalidArgumentException('The batch was created for a batch client with another URI or credential.');
        }

        $this->submitted = true;

        if ($this->subRequests === []) {
            throw new \InvalidArgumentException('Cannot submit an empty batch.');
        }

        return $this->subRequests;
    }

    /**
     * @return list<BlobClient|string>
     *
     * @internal
     */
    public function blobs(): array
    {
        return $this->blobs;
    }

    private function add(BlobClient|string $blob, RequestInterface $subRequest): void
    {
        if ($this->submitted) {
            throw new \LogicException('The batch has already been submitted.');
        }

        if (count($this->subRequests) >= BlobBatchClient::MAX_BATCH_SIZE) {
            throw new \InvalidArgumentException(sprintf('A batch can contain at most %d operations.', BlobBatchClient::MAX_BATCH_SIZE));
        }

        $this->subRequests[] = $subRequest;
        $this->blobs[] = $blob;
    }

    private function getBlobUri(BlobClient|string $blob): UriInterface
    {
        $scopeUri = $this->client->uri;
        $scopePath = rtrim($scopeUri->getPath(), '/').'/';

        if (is_string($blob)) {
            return $scopeUri->withPath($scopePath.$this->validateBlobName($blob));
        }

        if ($blob->uri->getAuthority() !== $scopeUri->getAuthority()) {
            throw new \InvalidArgumentException(sprintf('Blob client for "%s" points at a different endpoint than the batch client.', $blob->blobName));
        }

        if (! str_starts_with($blob->uri->getPath(), $scopePath)) {
            throw new \InvalidArgumentException(sprintf(
                'Blob "%s" is not in %s.',
                $blob->blobName,
                $this->client->containerName !== null ? "container \"{$this->client->containerName}\"" : 'the storage account',
            ));
        }

        return $blob->uri->withQuery(implode('&', array_filter(
            [$scopeUri->getQuery(), ...self::blobSelectors($blob->uri)],
            static fn (string $part): bool => $part !== '',
        )));
    }

    private function validateBlobName(string $blob): string
    {
        $name = ltrim($blob, '/');

        if ($name === '') {
            throw new \InvalidArgumentException('Blob name cannot be empty.');
        }

        if ($this->client->containerName === null && ! str_contains(rtrim($name, '/'), '/')) {
            throw new \InvalidArgumentException(sprintf('Blob name "%s" must be "<container>/<blob>" for a storage account batch client.', $blob));
        }

        return $name;
    }

    /**
     * @return list<string>
     */
    private static function blobSelectors(UriInterface $uri): array
    {
        $query = Query::parse($uri->getQuery());
        $selectors = [];

        foreach (self::BLOB_SELECTORS as $name) {
            if (is_string($query[$name] ?? null) && $query[$name] !== '') {
                $selectors[] = "{$name}={$query[$name]}";
            }
        }

        return $selectors;
    }
}
