<?php

declare(strict_types=1);

namespace AzureOss\Storage\File\Share;

use AzureOss\Identity\TokenCredential;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Helpers\ConnectionStringHelper;
use AzureOss\Storage\File\Share\Exceptions\InvalidConnectionStringException;
use AzureOss\Storage\File\Share\Models\ShareClientOptions;
use AzureOss\Storage\File\Share\Models\ShareServiceClientOptions;
use Psr\Http\Message\UriInterface;

/**
 * Provides service-level access to file shares in an Azure Storage account.
 */
final class ShareServiceClient
{
    /**
     * @param  UriInterface  $uri  File service endpoint, including any SAS query string.
     * @param  StorageSharedKeyCredential|TokenCredential|null  $credential  Credential used to authorize requests, or null for SAS access.
     * @param  ShareServiceClientOptions  $options  Client transport and service-version options.
     */
    public function __construct(
        public UriInterface $uri,
        public readonly StorageSharedKeyCredential|TokenCredential|null $credential = null,
        private readonly ShareServiceClientOptions $options = new ShareServiceClientOptions,
    ) {
        $this->uri = $uri->withPath(rtrim($uri->getPath(), '/').'/');
    }

    /**
     * Creates a client from an Azure Storage connection string.
     *
     * @throws InvalidConnectionStringException When the connection string does not contain a usable File endpoint and credential.
     */
    public static function fromConnectionString(string $connectionString, ShareServiceClientOptions $options = new ShareServiceClientOptions): self
    {
        $uri = ConnectionStringHelper::getFileEndpoint($connectionString);
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

    /** Creates a client for the named file share without making a service request. */
    public function getShareClient(string $shareName): ShareClient
    {
        return new ShareClient(
            $this->uri->withPath($this->uri->getPath().trim($shareName, '/')),
            $this->credential,
            new ShareClientOptions($this->options->httpClientOptions, $this->options->apiVersion),
        );
    }
}
