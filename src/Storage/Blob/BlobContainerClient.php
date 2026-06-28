<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob;

use AzureOss\Identity\TokenCredential;
use AzureOss\Storage\Blob\Exceptions\BlobStorageException;
use AzureOss\Storage\Blob\Exceptions\BlobStorageExceptionDeserializer;
use AzureOss\Storage\Blob\Exceptions\InvalidBlobUriException;
use AzureOss\Storage\Blob\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\Blob\Helpers\BlobUriParserHelper;
use AzureOss\Storage\Blob\Helpers\MetadataHelper;
use AzureOss\Storage\Blob\Models\Blob;
use AzureOss\Storage\Blob\Models\BlobClientOptions;
use AzureOss\Storage\Blob\Models\BlobContainerClientOptions;
use AzureOss\Storage\Blob\Models\BlobContainerProperties;
use AzureOss\Storage\Blob\Models\BlobErrorCode;
use AzureOss\Storage\Blob\Models\BlobInclude;
use AzureOss\Storage\Blob\Models\BlobLeaseClientOptions;
use AzureOss\Storage\Blob\Models\BlobPrefix;
use AzureOss\Storage\Blob\Models\BlockBlobClientOptions;
use AzureOss\Storage\Blob\Models\CreateContainerOptions;
use AzureOss\Storage\Blob\Models\DeleteContainerOptions;
use AzureOss\Storage\Blob\Models\GetBlobsOptions;
use AzureOss\Storage\Blob\Models\GetContainerPropertiesOptions;
use AzureOss\Storage\Blob\Models\PublicAccessType;
use AzureOss\Storage\Blob\Models\RequestConditionSet;
use AzureOss\Storage\Blob\Models\SetContainerMetadataOptions;
use AzureOss\Storage\Blob\Models\TaggedBlob;
use AzureOss\Storage\Blob\Responses\FindBlobsByTagBody;
use AzureOss\Storage\Blob\Responses\ListBlobsResponseBody;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use AzureOss\Storage\Blob\Specialized\BlobLeaseClient;
use AzureOss\Storage\Blob\Specialized\BlockBlobClient;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Helpers\StorageUriParserHelper;
use AzureOss\Storage\Common\Middleware\ClientFactory;
use AzureOss\Storage\Common\Sas\SasProtocol;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\UriInterface;

/**
 * Provides operations for an Azure Blob Storage container and the blobs within it.
 */
final class BlobContainerClient
{
    public const ROOT_BLOB_CONTAINER_NAME = '$root';

    public const LOGS_BLOB_CONTAINER_NAME = '$logs';

    public const WEB_BLOB_CONTAINER_NAME = '$web';

    private readonly Client $client;

    public readonly string $containerName;

    /**
     * @param  UriInterface  $uri  URI of the container, including any SAS query string.
     * @param  StorageSharedKeyCredential|TokenCredential|null  $credential  Credential used to authorize requests, or null for anonymous/SAS access.
     * @param  BlobContainerClientOptions  $options  Client transport and service-version options.
     *
     * @throws InvalidBlobUriException When the URI does not identify a container.
     */
    public function __construct(
        public readonly UriInterface $uri,
        public readonly StorageSharedKeyCredential|TokenCredential|null $credential = null,
        private readonly BlobContainerClientOptions $options = new BlobContainerClientOptions,
    ) {
        $this->containerName = BlobUriParserHelper::getContainerName($uri);
        $this->client = (new ClientFactory)->create($uri, $credential, new BlobStorageExceptionDeserializer, $this->options->httpClientOptions, $this->options->apiVersion);
    }

    /** Creates a general-purpose client for a blob without making a service request. */
    public function getBlobClient(string $blobName): BlobClient
    {
        return new BlobClient(
            $this->getBlobUri($blobName),
            $this->credential,
            new BlobClientOptions($this->options->httpClientOptions, $this->options->apiVersion),
        );
    }

    /** Creates a block blob client without making a service request. */
    public function getBlockBlobClient(string $blobName): BlockBlobClient
    {
        return new BlockBlobClient(
            $this->getBlobUri($blobName),
            $this->credential,
            new BlockBlobClientOptions($this->options->httpClientOptions, $this->options->apiVersion),
        );
    }

    /** Creates a lease client for this container without making a service request. */
    public function getBlobLeaseClient(?string $leaseId = null): BlobLeaseClient
    {
        return new BlobLeaseClient(
            $this->uri,
            $this->credential,
            $leaseId,
            container: true,
            options: new BlobLeaseClientOptions($this->options->httpClientOptions, $this->options->apiVersion),
        );
    }

    private function getBlobUri(string $blobName): UriInterface
    {
        return $this->uri->withPath($this->uri->getPath().'/'.ltrim($blobName, '/'));
    }

    /**
     * Creates the container.
     */
    public function create(CreateContainerOptions $options = new CreateContainerOptions): void
    {
        $this->createAsync($options)->wait();
    }

    /**
     * Asynchronously creates the container.
     */
    public function createAsync(CreateContainerOptions $options = new CreateContainerOptions): PromiseInterface
    {
        $headers = [];
        if ($options->publicAccessType !== PublicAccessType::NONE) {
            $headers['x-ms-blob-public-access'] = $options->publicAccessType->value;
        }

        return $this->client->putAsync($this->uri, [
            RequestOptions::QUERY => [
                'restype' => 'container',
            ],
            RequestOptions::HEADERS => $headers,
        ]);
    }

    /**
     * Creates the container if it does not already exist.
     */
    public function createIfNotExists(CreateContainerOptions $options = new CreateContainerOptions): void
    {
        $this->createIfNotExistsAsync($options)->wait();
    }

    /**
     * Asynchronously creates the container if it does not already exist.
     */
    public function createIfNotExistsAsync(CreateContainerOptions $options = new CreateContainerOptions): PromiseInterface
    {
        return $this->createAsync($options)
            ->otherwise(function (\Throwable $e) {
                if ($e instanceof BlobStorageException && $e->errorCode === BlobErrorCode::ContainerAlreadyExists) {
                    return;
                }

                throw $e;
            });
    }

    /** Deletes the container. */
    public function delete(DeleteContainerOptions $options = new DeleteContainerOptions): void
    {
        $this->deleteAsync($options)->wait();
    }

    /** Asynchronously deletes the container. */
    public function deleteAsync(DeleteContainerOptions $options = new DeleteContainerOptions): PromiseInterface
    {
        return $this->client->deleteAsync($this->uri, [
            RequestOptions::QUERY => [
                'restype' => 'container',
            ],
            RequestOptions::HEADERS => $options->conditions?->toHeaders(
                'BlobContainerClient::delete',
                RequestConditionSet::DATES_AND_LEASE,
            ) ?? [],
        ]);
    }

    /** Deletes the container if it exists. */
    public function deleteIfExists(DeleteContainerOptions $options = new DeleteContainerOptions): void
    {
        $this->deleteIfExistsAsync($options)->wait();
    }

    /** Asynchronously deletes the container if it exists. */
    public function deleteIfExistsAsync(DeleteContainerOptions $options = new DeleteContainerOptions): PromiseInterface
    {
        return $this->deleteAsync($options)
            ->otherwise(function (\Throwable $e) {
                if ($e instanceof BlobStorageException && $e->errorCode === BlobErrorCode::ContainerNotFound) {
                    return;
                }

                throw $e;
            });
    }

    /** Determines whether the container exists. */
    public function exists(): bool
    {
        /** @phpstan-ignore-next-line */
        return $this->existsAsync()->wait();
    }

    /** Asynchronously determines whether the container exists. */
    public function existsAsync(): PromiseInterface
    {
        return $this->client
            ->headAsync($this->uri, [
                RequestOptions::QUERY => [
                    'restype' => 'container',
                ],
            ])
            ->then(fn () => true)
            ->otherwise(function (\Throwable $e) {
                if ($e instanceof BlobStorageException && $e->errorCode === BlobErrorCode::ContainerNotFound) {
                    return false;
                }

                throw $e;
            });
    }

    /** Gets the container's properties and metadata. */
    public function getProperties(GetContainerPropertiesOptions $options = new GetContainerPropertiesOptions): BlobContainerProperties
    {
        /** @phpstan-ignore-next-line */
        return $this->getPropertiesAsync($options)->wait();
    }

    /** Asynchronously gets the container's properties and metadata. */
    public function getPropertiesAsync(GetContainerPropertiesOptions $options = new GetContainerPropertiesOptions): PromiseInterface
    {
        return $this->client
            ->headAsync($this->uri, [
                RequestOptions::QUERY => [
                    'restype' => 'container',
                ],
                RequestOptions::HEADERS => $options->conditions?->toHeaders(
                    'BlobContainerClient::getProperties',
                    RequestConditionSet::LEASE_ONLY,
                ) ?? [],
            ])
            ->then(BlobContainerProperties::fromResponseHeaders(...));
    }

    /**
     * Replaces all user-defined metadata on the container.
     *
     * @param  array<string, string>  $metadata
     */
    public function setMetadata(array $metadata, SetContainerMetadataOptions $options = new SetContainerMetadataOptions): void
    {
        $this->setMetadataAsync($metadata, $options)->wait();
    }

    /**
     * Asynchronously replaces all user-defined metadata on the container.
     *
     * @param  array<string, string>  $metadata
     */
    public function setMetadataAsync(array $metadata, SetContainerMetadataOptions $options = new SetContainerMetadataOptions): PromiseInterface
    {
        return $this->client->putAsync($this->uri, [
            RequestOptions::QUERY => [
                'restype' => 'container',
                'comp' => 'metadata',
            ],
            RequestOptions::HEADERS => [
                ...MetadataHelper::metadataToHeaders($metadata),
                ...($options->conditions?->toHeaders(
                    'BlobContainerClient::setMetadata',
                    RequestConditionSet::MODIFIED_SINCE_AND_LEASE,
                ) ?? []),
            ],
        ]);
    }

    /**
     * Lists blobs in a flat sequence, following continuation markers automatically.
     *
     * @return \Generator<Blob>
     */
    public function getBlobs(?string $prefix = null, GetBlobsOptions $options = new GetBlobsOptions): \Generator
    {
        $nextMarker = '';

        while (true) {
            $response = $this->listBlobs($prefix, null, $nextMarker, $options->pageSize, $options->includes);
            $nextMarker = $response->nextMarker;

            foreach ($response->blobs as $blob) {
                yield $blob;
            }

            if ($nextMarker === '') {
                break;
            }
        }
    }

    /**
     * Lists blobs as a hierarchy of blob items and virtual-directory prefixes.
     *
     * @return \Generator<Blob|BlobPrefix>
     */
    public function getBlobsByHierarchy(?string $prefix = null, string $delimiter = '/', GetBlobsOptions $options = new GetBlobsOptions): \Generator
    {
        $nextMarker = '';

        while (true) {
            $response = $this->listBlobs($prefix, $delimiter, $nextMarker, $options->pageSize, $options->includes);
            $nextMarker = $response->nextMarker;

            foreach ($response->blobs as $blob) {
                yield $blob;
            }

            foreach ($response->blobPrefixes as $blobPrefix) {
                yield $blobPrefix;
            }

            if ($nextMarker === '') {
                break;
            }
        }
    }

    /**
     * @param  BlobInclude[]  $includes
     */
    private function listBlobs(?string $prefix, ?string $delimiter, string $marker, ?int $maxResults, array $includes): ListBlobsResponseBody
    {
        $response = $this->client->get($this->uri, [
            RequestOptions::QUERY => [
                'restype' => 'container',
                'comp' => 'list',
                'prefix' => $prefix,
                'marker' => $marker !== '' ? $marker : null,
                'delimiter' => $delimiter,
                'maxresults' => $maxResults,
                'include' => $includes !== []
                    ? implode(',', array_map(static fn (BlobInclude $include): string => $include->value, $includes))
                    : null,
            ],
        ]);

        return ListBlobsResponseBody::fromXml(new \SimpleXMLElement($response->getBody()->getContents()));
    }

    /** Returns whether this client has a shared-key credential capable of signing a container SAS. */
    public function canGenerateSasUri(): bool
    {
        return $this->credential instanceof StorageSharedKeyCredential;
    }

    /**
     * Generates a URI for this container containing a signed service SAS query string.
     *
     * @throws UnableToGenerateSasException When the client does not have a shared-key credential.
     */
    public function generateSasUri(BlobSasBuilder $blobSasBuilder): UriInterface
    {
        if (! $this->credential instanceof StorageSharedKeyCredential) {
            throw new UnableToGenerateSasException;
        }

        if (StorageUriParserHelper::isDevelopmentUri($this->uri)) {
            $blobSasBuilder->setProtocol(SasProtocol::HTTPS_AND_HTTP);
        }

        $sas = $blobSasBuilder
            ->setContainerName($this->containerName)
            ->build($this->credential);

        return $this->uri->withQuery(Query::build([
            ...Query::parse($this->uri->getQuery()),
            ...Query::parse($sas),
        ]));
    }

    /**
     * Finds blobs in this container whose index tags match the SQL expression.
     *
     * @return \Generator<TaggedBlob>
     */
    public function findBlobsByTag(string $tagFilterSqlExpression): \Generator
    {
        $nextMarker = '';

        while (true) {
            $response = $this->client->get($this->uri, [
                RequestOptions::QUERY => [
                    'restype' => 'container',
                    'comp' => 'blobs',
                    'where' => $tagFilterSqlExpression,
                    'marker' => $nextMarker !== '' ? $nextMarker : null,
                ],
            ]);

            $body = FindBlobsByTagBody::fromXml(new \SimpleXMLElement($response->getBody()->getContents()));
            $nextMarker = $body->nextMarker;

            foreach ($body->blobs as $blob) {
                yield $blob;
            }

            if ($nextMarker === '') {
                break;
            }
        }
    }
}
