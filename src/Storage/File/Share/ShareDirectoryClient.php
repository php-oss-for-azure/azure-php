<?php

declare(strict_types=1);

namespace AzureOss\Storage\File\Share;

use AzureOss\Identity\TokenCredential;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Helpers\StorageUriParserHelper;
use AzureOss\Storage\Common\Sas\SasProtocol;
use AzureOss\Storage\File\Share\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\File\Share\Helpers\ShareUriParserHelper;
use AzureOss\Storage\File\Share\Models\ShareDirectoryClientOptions;
use AzureOss\Storage\File\Share\Sas\ShareSasBuilder;
use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\UriInterface;

/**
 * Provides operations for an Azure file share directory.
 */
final readonly class ShareDirectoryClient
{
    public string $shareName;

    public string $directoryPath;

    /**
     * @param  UriInterface  $uri  URI of the directory, including any SAS query string.
     * @param  StorageSharedKeyCredential|TokenCredential|null  $credential  Credential used to authorize requests, or null for anonymous/SAS access.
     * @param  ShareDirectoryClientOptions  $options  Client transport and service-version options.
     */
    public function __construct(
        public UriInterface $uri,
        public StorageSharedKeyCredential|TokenCredential|null $credential = null,
        private ShareDirectoryClientOptions $options = new ShareDirectoryClientOptions,
    ) {
        $this->shareName = ShareUriParserHelper::getShareName($uri);
        $this->directoryPath = ShareUriParserHelper::getResourcePath($uri);
    }

    /** Creates a client for a child directory without making a service request. */
    public function getDirectoryClient(string $directoryPath): self
    {
        $options = new ShareDirectoryClientOptions(
            $this->options->httpClientOptions,
            $this->options->apiVersion,
        );

        return new self(
            $this->uri->withPath($this->uri->getPath().'/'.ltrim($directoryPath, '/')),
            $this->credential,
            $options,
        );
    }

    /** Creates a client for a file inside this directory without making a service request. */
    public function getFileClient(string $fileName): ShareFileClient
    {
        return new ShareFileClient(
            $this->uri->withPath($this->uri->getPath().'/'.ltrim($fileName, '/')),
            $this->credential,
        );
    }

    /** Returns whether this client has a shared-key credential capable of signing a SAS for this directory path. */
    public function canGenerateSasUri(): bool
    {
        return $this->credential instanceof StorageSharedKeyCredential;
    }

    /**
     * Generates a URI for this directory containing a signed service SAS query string.
     *
     * Azure Files service SAS supports share (`sr=s`) and file (`sr=f`) resources.
     * Directory paths are therefore signed using the file-path form, matching the Azure .NET SDK.
     *
     * @throws UnableToGenerateSasException When the client does not have a shared-key credential.
     */
    public function generateSasUri(ShareSasBuilder $shareSasBuilder): UriInterface
    {
        if (! $this->credential instanceof StorageSharedKeyCredential) {
            throw new UnableToGenerateSasException;
        }

        if (StorageUriParserHelper::isDevelopmentUri($this->uri)) {
            $shareSasBuilder->setProtocol(SasProtocol::HTTPS_AND_HTTP);
        }

        $sas = $shareSasBuilder
            ->setShareName($this->shareName)
            ->setFilePath($this->directoryPath)
            ->build($this->credential);

        return $this->uri->withQuery(Query::build([
            ...Query::parse($this->uri->getQuery()),
            ...Query::parse($sas),
        ]));
    }
}
