<?php

declare(strict_types=1);

namespace AzureOss\Storage\File\Share;

use AzureOss\Identity\TokenCredential;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Helpers\StorageUriParserHelper;
use AzureOss\Storage\Common\Sas\SasProtocol;
use AzureOss\Storage\File\Share\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\File\Share\Helpers\ShareUriParserHelper;
use AzureOss\Storage\File\Share\Sas\ShareSasBuilder;
use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\UriInterface;

/**
 * Provides operations for an Azure file share file.
 */
final readonly class ShareFileClient
{
    public string $shareName;

    public string $filePath;

    /**
     * @param  UriInterface  $uri  URI of the file, including any SAS query string.
     * @param  StorageSharedKeyCredential|TokenCredential|null  $credential  Credential used to authorize requests, or null for anonymous/SAS access.
     */
    public function __construct(
        public UriInterface $uri,
        public StorageSharedKeyCredential|TokenCredential|null $credential = null,
    ) {
        $this->shareName = ShareUriParserHelper::getShareName($uri);
        $this->filePath = ShareUriParserHelper::getResourcePath($uri);
    }

    /** Returns whether this client has a shared-key credential capable of signing a file SAS. */
    public function canGenerateSasUri(): bool
    {
        return $this->credential instanceof StorageSharedKeyCredential;
    }

    /**
     * Generates a URI for this file containing a signed service SAS query string.
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
            ->setFilePath($this->filePath)
            ->build($this->credential);

        return $this->uri->withQuery(Query::build([
            ...Query::parse($this->uri->getQuery()),
            ...Query::parse($sas),
        ]));
    }
}
