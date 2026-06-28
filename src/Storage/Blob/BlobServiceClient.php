<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob;

use AzureOss\Identity\TokenCredential;
use AzureOss\Storage\Blob\Exceptions\BlobStorageExceptionDeserializer;
use AzureOss\Storage\Blob\Exceptions\InvalidConnectionStringException;
use AzureOss\Storage\Blob\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\Blob\Models\BlobContainer;
use AzureOss\Storage\Blob\Models\BlobContainerClientOptions;
use AzureOss\Storage\Blob\Models\BlobContainerInclude;
use AzureOss\Storage\Blob\Models\BlobServiceClientOptions;
use AzureOss\Storage\Blob\Models\GetBlobContainersOptions;
use AzureOss\Storage\Blob\Models\TaggedBlob;
use AzureOss\Storage\Blob\Responses\FindBlobsByTagBody;
use AzureOss\Storage\Blob\Responses\ListContainersResponseBody;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Helpers\ConnectionStringHelper;
use AzureOss\Storage\Common\Helpers\StorageUriParserHelper;
use AzureOss\Storage\Common\Middleware\ClientFactory;
use AzureOss\Storage\Common\Sas\AccountSasBuilder;
use AzureOss\Storage\Common\Sas\AccountSasServices;
use AzureOss\Storage\Common\Sas\SasProtocol;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\UriInterface;

/**
 * Provides service-level operations for an Azure Blob Storage account.
 *
 * Create this client from a service endpoint and credential, or use
 * {@see self::fromConnectionString()} when a storage connection string is available.
 */
final class BlobServiceClient
{
    private readonly Client $client;

    /**
     * @param  UriInterface  $uri  Blob service endpoint, including any SAS query string.
     * @param  StorageSharedKeyCredential|TokenCredential|null  $credential  Credential used to authorize requests, or null for anonymous/SAS access.
     * @param  BlobServiceClientOptions  $options  Client transport and service-version options.
     */
    public function __construct(
        public UriInterface $uri,
        public readonly StorageSharedKeyCredential|TokenCredential|null $credential = null,
        private readonly BlobServiceClientOptions $options = new BlobServiceClientOptions,
    ) {
        // must always include the forward slash (/) to separate the host name from the path and query portions of the URI.
        $this->uri = $uri->withPath(rtrim($uri->getPath(), '/').'/');
        $this->client = (new ClientFactory)->create($this->uri, $credential, new BlobStorageExceptionDeserializer, $this->options->httpClientOptions, $this->options->apiVersion);
    }

    /**
     * Creates a client from an Azure Storage connection string.
     *
     * @throws InvalidConnectionStringException When the connection string does not contain a usable Blob endpoint and credential.
     */
    public static function fromConnectionString(string $connectionString, BlobServiceClientOptions $options = new BlobServiceClientOptions): self
    {
        $uri = ConnectionStringHelper::getBlobEndpoint($connectionString);
        if ($uri === null) {
            throw new InvalidConnectionStringException;
        }

        $sas = ConnectionStringHelper::getSas($connectionString);
        if ($sas !== null) {
            return new self($uri->withQuery($sas), options: $options);
        }

        $accountName = ConnectionStringHelper::getAccountName($connectionString);
        $accountKey = ConnectionStringHelper::getAccountKey($connectionString);
        if ($accountName !== null && $accountKey !== null) {
            return new self($uri, new StorageSharedKeyCredential($accountName, $accountKey), $options);
        }

        throw new InvalidConnectionStringException;
    }

    /**
     * Creates a client for the named blob container without making a service request.
     */
    public function getContainerClient(string $containerName): BlobContainerClient
    {
        return new BlobContainerClient(
            $this->uri->withPath($this->uri->getPath().$containerName),
            $this->credential,
            new BlobContainerClientOptions($this->options->httpClientOptions, $this->options->apiVersion),
        );
    }

    /**
     * Lists containers in the storage account, following continuation markers automatically.
     *
     * @param  string|null  $prefix  Optional prefix used to filter container names.
     * @return \Generator<BlobContainer>
     */
    public function getBlobContainers(?string $prefix = null, GetBlobContainersOptions $options = new GetBlobContainersOptions): \Generator
    {
        $nextMarker = '';

        while (true) {
            $response = $this->client->get($this->uri, [
                RequestOptions::QUERY => [
                    'comp' => 'list',
                    'marker' => $nextMarker !== '' ? $nextMarker : null,
                    'prefix' => $prefix,
                    'maxresults' => $options->pageSize,
                    'include' => $options->includes !== []
                        ? implode(',', array_map(static fn (BlobContainerInclude $include): string => $include->value, $options->includes))
                        : null,
                ],
            ]);
            $body = ListContainersResponseBody::fromXml(new \SimpleXMLElement($response->getBody()->getContents()));
            $nextMarker = $body->nextMarker;

            foreach ($body->containers as $container) {
                yield $container;
            }

            if ($nextMarker === '') {
                break;
            }
        }
    }

    /**
     * Restores a soft-deleted container identified by its name and deleted version.
     *
     * The version is returned by {@see self::getBlobContainers()} when
     * {@see BlobContainerInclude::DELETED} is requested.
     */
    public function undeleteBlobContainer(string $deletedContainerName, string $deletedContainerVersion): BlobContainerClient
    {
        /** @phpstan-ignore-next-line */
        return $this->undeleteBlobContainerAsync($deletedContainerName, $deletedContainerVersion)->wait();
    }

    /** Asynchronously restores a soft-deleted container and returns a client for it. */
    public function undeleteBlobContainerAsync(string $deletedContainerName, string $deletedContainerVersion): PromiseInterface
    {
        $container = $this->getContainerClient($deletedContainerName);

        return $this->client->putAsync($container->uri, [
            RequestOptions::QUERY => [
                'restype' => 'container',
                'comp' => 'undelete',
            ],
            RequestOptions::HEADERS => [
                'x-ms-deleted-container-name' => $deletedContainerName,
                'x-ms-deleted-container-version' => $deletedContainerVersion,
            ],
        ])->then(fn () => $container);
    }

    /**
     * Finds blobs across the storage account whose index tags match the SQL expression.
     *
     * @return \Generator<TaggedBlob>
     */
    public function findBlobsByTag(string $tagFilterSqlExpression): \Generator
    {
        $nextMarker = '';

        while (true) {
            $response = $this->client->get($this->uri, [
                RequestOptions::QUERY => [
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

    /**
     * Returns whether this client has a shared-key credential capable of signing an account SAS.
     */
    public function canGenerateAccountSasUri(): bool
    {
        return $this->credential instanceof StorageSharedKeyCredential;
    }

    /**
     * Generates a Blob service URI containing a signed account SAS query string.
     *
     * @throws UnableToGenerateSasException When the client does not have a shared-key credential.
     */
    public function generateAccountSasUri(AccountSasBuilder $accountSasBuilder): UriInterface
    {
        if (! $this->credential instanceof StorageSharedKeyCredential) {
            throw new UnableToGenerateSasException;
        }

        if (StorageUriParserHelper::isDevelopmentUri($this->uri)) {
            $accountSasBuilder->setProtocol(SasProtocol::HTTPS_AND_HTTP);
        }

        $sas = $accountSasBuilder
            ->setServices(new AccountSasServices(blob: true))
            ->build($this->credential);

        return new Uri("$this->uri?$sas");
    }
}
