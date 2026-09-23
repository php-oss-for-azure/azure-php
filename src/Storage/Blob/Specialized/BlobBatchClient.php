<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Specialized;

use AzureOss\Identity\TokenCredential;
use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\Exceptions\BlobBatchException;
use AzureOss\Storage\Blob\Exceptions\BlobStorageException;
use AzureOss\Storage\Blob\Exceptions\BlobStorageExceptionDeserializer;
use AzureOss\Storage\Blob\Exceptions\DeserializationException;
use AzureOss\Storage\Blob\Models\BlobBatchClientOptions;
use AzureOss\Storage\Blob\Models\BlobBatchFailure;
use AzureOss\Storage\Blob\Models\DeleteBlobOptions;
use AzureOss\Storage\Blob\Models\DeleteSnapshotsOption;
use AzureOss\Storage\Blob\Requests\BlobBatchRequestBody;
use AzureOss\Storage\Blob\Responses\BlobBatchResponseBody;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Middleware\ClientFactory;
use AzureOss\Storage\Common\Middleware\RequestSigner;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Submits blob operations to a container or storage account in a single Blob Batch request.
 *
 * A batch is not atomic: the service applies each operation independently, in no guaranteed order,
 * and the other operations still apply when some fail. Each operation is authorized on its own with
 * the client's credential or SAS; a SAS needs Write (`w`) permission for the batch request itself
 * and Delete (`d`) permission for each deleted blob.
 */
final class BlobBatchClient
{
    /**
     * Maximum number of operations in one batch, as enforced by the service.
     *
     * The service also limits the request body to 4 MB; that limit is left to the service to enforce.
     */
    public const MAX_BATCH_SIZE = 256;

    private readonly Client $client;

    private readonly ?RequestSigner $signer;

    /**
     * @param  UriInterface  $uri  URI of the container or storage account, including any SAS query string. Blob names are relative to it.
     * @param  StorageSharedKeyCredential|TokenCredential|null  $credential  Credential used to authorize the batch and each operation, or null for anonymous/SAS access.
     * @param  string|null  $containerName  Name of the container `$uri` points at, or null when it points at the storage account.
     *                                      A container batch client takes blob names; an account batch client takes `<container>/<blob>`.
     * @param  BlobBatchClientOptions  $options  Client transport and service-version options.
     */
    public function __construct(
        public readonly UriInterface $uri,
        public readonly StorageSharedKeyCredential|TokenCredential|null $credential = null,
        public readonly ?string $containerName = null,
        private readonly BlobBatchClientOptions $options = new BlobBatchClientOptions,
    ) {
        $factory = new ClientFactory;
        $this->signer = $factory->createRequestSigner($credential);
        $this->client = $factory->create(
            $uri,
            $credential,
            new BlobStorageExceptionDeserializer,
            $this->options->httpClientOptions,
            $this->options->apiVersion,
            $this->signer,
        );
    }

    /** Creates an empty batch to submit with this client, without making a service request. */
    public function createBatch(): BlobBatch
    {
        return new BlobBatch($this);
    }

    /**
     * Submits the operations of a batch in a single request.
     *
     * The batch can be submitted once, through any batch client with the same URI and credential.
     * The retry middleware may replay a batch whose response was lost; blobs the first attempt
     * deleted are then reported as failed with `BlobNotFound`. A token credential that cannot
     * produce a token throws its own exception.
     *
     * @throws BlobBatchException When the service accepted the batch but one or more operations failed; the others were applied.
     * @throws BlobStorageException When the service rejected the whole batch, for example because authorization failed; no operation was applied.
     * @throws DeserializationException When the batch response cannot be parsed.
     * @throws \InvalidArgumentException When the batch is empty, was already submitted, or belongs to a client with another URI or credential.
     */
    public function submitBatch(BlobBatch $batch): void
    {
        $this->submitBatchAsync($batch)->wait();
    }

    /**
     * Asynchronously submits the operations of a batch in a single request.
     *
     * Throws `\InvalidArgumentException` immediately when the batch is empty, was already submitted, or belongs
     * to a client with another URI or credential. The request is signed and sent when the promise is waited on
     * or the Guzzle task queue runs. See `submitBatch()` for the batch semantics and retry caveat.
     *
     * @return PromiseInterface<void, mixed> Fulfills when every operation succeeded. Rejects with `BlobBatchException` when the
     *                                       service accepted the batch but some operations failed, `BlobStorageException` when
     *                                       the whole batch was rejected, `DeserializationException` when the response cannot be
     *                                       parsed, and the credential's exception when a token cannot be acquired.
     */
    public function submitBatchAsync(BlobBatch $batch): PromiseInterface
    {
        $subRequests = $batch->submitTo($this);
        $blobs = $batch->blobs();

        return Create::promiseFor(null)->then(
            fn (): PromiseInterface => $this->send(array_map($this->signSubRequest(...), $subRequests), $blobs),
        );
    }

    /**
     * Deletes up to `MAX_BATCH_SIZE` blobs in a single request.
     *
     * Blobs are given as blob clients in this client's scope, which may target a snapshot or version, or as
     * names relative to this client's URI: `<blob>` for a container batch client, `<container>/<blob>` for an
     * account batch client. Use `createBatch()` to give each blob its own conditions. The retry caveat of
     * `submitBatch()` applies.
     *
     * @param  array<BlobClient|string>  $blobs  The blobs to delete; failures report their position in iteration order.
     * @param  DeleteSnapshotsOption|null  $snapshotsOption  How to treat the snapshots of every blob; required to delete a blob that has snapshots.
     *
     * @throws BlobBatchException When the service accepted the batch but one or more blobs could not be deleted; the others were deleted.
     * @throws BlobStorageException When the service rejected the whole batch, for example because authorization failed; no blob was deleted.
     * @throws DeserializationException When the batch response cannot be parsed.
     * @throws \InvalidArgumentException When `$blobs` is empty, holds more than `MAX_BATCH_SIZE` blobs, or holds an invalid name or out-of-scope blob client.
     */
    public function deleteBlobs(array $blobs, ?DeleteSnapshotsOption $snapshotsOption = null): void
    {
        $this->deleteBlobsAsync($blobs, $snapshotsOption)->wait();
    }

    /**
     * Asynchronously deletes up to `MAX_BATCH_SIZE` blobs in a single request.
     *
     * Throws `\InvalidArgumentException` immediately when `$blobs` is empty, holds more than `MAX_BATCH_SIZE` blobs,
     * or holds an invalid name or out-of-scope blob client. See `deleteBlobs()` for the accepted blobs and the retry caveat.
     *
     * @param  array<BlobClient|string>  $blobs  The blobs to delete, as accepted by `deleteBlobs()`.
     * @param  DeleteSnapshotsOption|null  $snapshotsOption  How to treat the snapshots of every blob.
     * @return PromiseInterface<void, mixed> Fulfills when every blob was deleted; rejects as described for `submitBatchAsync()`.
     */
    public function deleteBlobsAsync(array $blobs, ?DeleteSnapshotsOption $snapshotsOption = null): PromiseInterface
    {
        $batch = $this->createBatch();
        $options = new DeleteBlobOptions(snapshotsOption: $snapshotsOption);

        foreach ($blobs as $blob) {
            $batch->deleteBlob($blob, $options);
        }

        return $this->submitBatchAsync($batch);
    }

    /**
     * @param  non-empty-list<RequestInterface>  $subRequests
     * @param  list<BlobClient|string>  $blobs
     * @return PromiseInterface<void, mixed>
     */
    private function send(array $subRequests, array $blobs): PromiseInterface
    {
        $body = new BlobBatchRequestBody($subRequests);
        $request = new Request('POST', $this->uri->withQuery(Query::build([
            ...Query::parse($this->uri->getQuery()),
            ...($this->containerName !== null ? ['restype' => 'container'] : []),
            'comp' => 'batch',
        ])), ['Content-Type' => $body->contentType()], $body->toString());

        return $this->client->sendAsync($request)->then(static function (ResponseInterface $response) use ($request, $subRequests, $blobs): void {
            $batchResponse = BlobBatchResponseBody::fromResponse($response, count($subRequests));

            if ($batchResponse->rejection !== null) {
                throw self::deserializeFailure($request, $batchResponse->rejection);
            }

            $failures = self::collectFailures($subRequests, $blobs, $batchResponse);

            if ($failures !== []) {
                throw new BlobBatchException(
                    self::describeFailures($subRequests, $failures),
                    $failures,
                    $failures[0]->exception,
                    $response->getHeaderLine('x-ms-request-id') !== '' ? $response->getHeaderLine('x-ms-request-id') : null,
                    $response->getStatusCode(),
                );
            }
        });
    }

    private function signSubRequest(RequestInterface $subRequest): RequestInterface
    {
        $request = $subRequest
            ->withHeader('x-ms-date', gmdate('D, d M Y H:i:s T', time()))
            ->withHeader('Content-Length', '0');

        return $this->signer?->sign($request) ?? $request;
    }

    /**
     * @param  list<RequestInterface>  $subRequests
     * @param  list<BlobClient|string>  $blobs
     * @return list<BlobBatchFailure>
     */
    private static function collectFailures(array $subRequests, array $blobs, BlobBatchResponseBody $body): array
    {
        $failures = [];

        foreach ($subRequests as $index => $subRequest) {
            $response = $body->responses[$index]
                ?? throw new DeserializationException("Blob batch response is missing sub-request $index.");

            if ($response->getStatusCode() >= 300) {
                $failures[] = new BlobBatchFailure($index, $blobs[$index], self::deserializeFailure($subRequest, $response));
            }
        }

        return $failures;
    }

    /**
     * @param  list<RequestInterface>  $subRequests
     * @param  non-empty-list<BlobBatchFailure>  $failures
     */
    private static function describeFailures(array $subRequests, array $failures): string
    {
        $described = array_map(
            static fn (BlobBatchFailure $failure): string => sprintf(
                '"%s" (%s)',
                self::describeSubRequest($subRequests[$failure->index]),
                $failure->exception->errorCodeValue ?? $failure->exception->statusCode,
            ),
            array_slice($failures, 0, 5),
        );
        $more = count($failures) - count($described);

        return sprintf(
            '%d of %d blob batch sub-requests failed: %s%s',
            count($failures),
            count($subRequests),
            implode(', ', $described),
            $more > 0 ? " and {$more} more" : '',
        );
    }

    private static function describeSubRequest(RequestInterface $subRequest): string
    {
        $query = Query::parse($subRequest->getUri()->getQuery());
        $selectors = [];

        foreach (BlobBatch::BLOB_SELECTORS as $name) {
            if (is_string($query[$name] ?? null)) {
                $selectors[] = "{$name}={$query[$name]}";
            }
        }

        return rawurldecode($subRequest->getUri()->getPath()).($selectors === [] ? '' : '?'.implode('&', $selectors));
    }

    private static function deserializeFailure(RequestInterface $request, ResponseInterface $response): BlobStorageException
    {
        $exception = (new BlobStorageExceptionDeserializer)->deserialize(RequestException::create($request, $response));

        return $exception instanceof BlobStorageException
            ? $exception
            : new BlobStorageException($exception->getMessage(), previous: $exception, statusCode: $response->getStatusCode());
    }
}
