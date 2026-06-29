<?php

declare(strict_types=1);

namespace AzureOss\Storage\File\Share;

use AzureOss\Identity\TokenCredential;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Helpers\StorageUriParserHelper;
use AzureOss\Storage\Common\Sas\SasProtocol;
use AzureOss\Storage\File\Share\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\File\Share\Helpers\ShareUriParserHelper;
use AzureOss\Storage\File\Share\Models\ShareClientOptions;
use AzureOss\Storage\File\Share\Models\ShareDirectoryClientOptions;
use AzureOss\Storage\File\Share\Sas\ShareSasBuilder;
use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\UriInterface;

/**
 * Provides operations for an Azure file share and the directories and files within it.
 */
final readonly class ShareClient
{
    public string $shareName;

    /**
     * @param  UriInterface  $uri  URI of the share, including any SAS query string.
     * @param  StorageSharedKeyCredential|TokenCredential|null  $credential  Credential used to authorize requests, or null for anonymous/SAS access.
     * @param  ShareClientOptions  $options  Client transport and service-version options.
     */
    public function __construct(
        public UriInterface $uri,
        public StorageSharedKeyCredential|TokenCredential|null $credential = null,
        private ShareClientOptions $options = new ShareClientOptions,
    ) {
        $this->shareName = ShareUriParserHelper::getShareName($uri);
    }

    /** Creates a client for a directory in this share without making a service request. */
    public function getDirectoryClient(string $directoryPath): ShareDirectoryClient
    {
        return new ShareDirectoryClient(
            $this->uri->withPath($this->uri->getPath().'/'.ltrim($directoryPath, '/')),
            $this->credential,
            new ShareDirectoryClientOptions($this->options->httpClientOptions, $this->options->apiVersion),
        );
    }

    /** Creates a client for a file in this share without making a service request. */
    public function getFileClient(string $filePath): ShareFileClient
    {
        return new ShareFileClient(
            $this->uri->withPath($this->uri->getPath().'/'.ltrim($filePath, '/')),
            $this->credential,
        );
    }

    /** Returns whether this client has a shared-key credential capable of signing a share SAS. */
    public function canGenerateSasUri(): bool
    {
        return $this->credential instanceof StorageSharedKeyCredential;
    }

    /**
     * Generates a URI for this share containing a signed service SAS query string.
     *
     * @throws UnableToGenerateSasException When the client does not have a shared-key credential.
     */
    public function generateSasUri(ShareSasBuilder $shareSasBuilder): UriInterface
    {
        if (! $this->credential instanceof StorageSharedKeyCredential) {
            throw new UnableToGenerateSasException;
        }

        $builder = clone $shareSasBuilder;

        if (StorageUriParserHelper::isDevelopmentUri($this->uri)) {
            $builder->setProtocol(SasProtocol::HTTPS_AND_HTTP);
        }

        $sas = $builder
            ->setShareName($this->shareName)
            ->setFilePath(null)
            ->build($this->credential);

        return $this->uri->withQuery(Query::build([
            ...Query::parse($this->uri->getQuery()),
            ...Query::parse($sas),
        ]));
    }
}
