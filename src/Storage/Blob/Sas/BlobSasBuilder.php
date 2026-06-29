<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Sas;

use AzureOss\Storage\Blob\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\Blob\Helpers\DateHelper;
use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Sas\SasIpRange;
use AzureOss\Storage\Common\Sas\SasProtocol;
use GuzzleHttp\Psr7\Query;

/**
 * Builds an Azure Blob Storage service shared access signature (SAS).
 */
final class BlobSasBuilder
{
    private string $version;

    private string $containerName;

    private \DateTimeInterface $expiresOn;

    private ?string $blobName = null;

    private ?\DateTimeInterface $startsOn = null;

    private ?string $permissions = null;

    private ?string $identifier = null;

    private ?string $cacheControl = null;

    private ?string $contentDisposition = null;

    private ?string $contentEncoding = null;

    private ?string $contentLanguage = null;

    private ?string $contentType = null;

    private ?string $encryptionScope = null;

    private ?SasIpRange $ipRange = null;

    private ?string $snapshot = null;

    private ?string $blobVersionId = null;

    private ?SasProtocol $protocol = null;

    /** Creates an empty blob service SAS builder. */
    public static function new(): self
    {
        return new self;
    }

    /** Sets the container name included in the canonical signed resource. */
    public function setContainerName(string $value): BlobSasBuilder
    {
        $this->containerName = $value;

        return $this;
    }

    /** Sets the blob name, or omits it to create a container SAS. */
    public function setBlobName(?string $value): BlobSasBuilder
    {
        $this->blobName = $value !== '' ? $value : null;

        return $this;
    }

    /** Sets the instant at which the SAS expires. */
    public function setExpiresOn(\DateTimeInterface $value): self
    {
        $this->expiresOn = $value;

        return $this;
    }

    /** Sets the operations permitted by the SAS. */
    public function setPermissions(string|BlobSasPermissions|BlobContainerSasPermissions $value): self
    {
        $this->permissions = (string) $value;

        return $this;
    }

    /** Associates the SAS with a stored access policy identifier. */
    public function setIdentifier(string $value): self
    {
        $this->identifier = $value;

        return $this;
    }

    /** Sets the earliest instant at which the SAS is valid. */
    public function setStartsOn(\DateTimeInterface $value): self
    {
        $this->startsOn = $value;

        return $this;
    }

    /** Overrides the Cache-Control response header for requests using the SAS. */
    public function setCacheControl(string $value): self
    {
        $this->cacheControl = $value;

        return $this;
    }

    /** Overrides the Content-Disposition response header for requests using the SAS. */
    public function setContentDisposition(string $value): self
    {
        $this->contentDisposition = $value;

        return $this;
    }

    /** Overrides the Content-Encoding response header for requests using the SAS. */
    public function setContentEncoding(string $value): self
    {
        $this->contentEncoding = $value;

        return $this;
    }

    /** Overrides the Content-Language response header for requests using the SAS. */
    public function setContentLanguage(string $value): self
    {
        $this->contentLanguage = $value;

        return $this;
    }

    /** Overrides the Content-Type response header for requests using the SAS. */
    public function setContentType(string $value): self
    {
        $this->contentType = $value;

        return $this;
    }

    /** Sets the encryption scope required for requests authorized by the SAS. */
    public function setEncryptionScope(string $value): self
    {
        $this->encryptionScope = $value;

        return $this;
    }

    /** Restricts requests to the specified source IP address or range. */
    public function setIPRange(SasIpRange $value): self
    {
        $this->ipRange = $value;

        return $this;
    }

    /** Sets the opaque snapshot identifier for a snapshot-specific SAS. */
    public function setSnapshot(?string $value): self
    {
        $this->snapshot = $value !== '' ? $value : null;

        if ($this->snapshot !== null) {
            $this->blobVersionId = null;
        }

        return $this;
    }

    /** Sets the opaque blob version identifier for a version-specific SAS. */
    public function setBlobVersionId(?string $value): self
    {
        $this->blobVersionId = $value !== '' ? $value : null;

        if ($this->blobVersionId !== null) {
            $this->snapshot = null;
        }

        return $this;
    }

    /** Restricts requests to HTTPS, or permits both HTTPS and HTTP. */
    public function setProtocol(SasProtocol $value): self
    {
        $this->protocol = $value;

        return $this;
    }

    /** Sets the Storage service version signed by the SAS. */
    public function setVersion(string $value): self
    {
        $this->version = $value;

        return $this;
    }

    /** Signs and returns the service SAS query string without a leading question mark. */
    public function build(StorageSharedKeyCredential $sharedKeyCredential): string
    {
        $this->validateState();

        $signedStart = $this->startsOn !== null ? DateHelper::formatAs8601Zulu($this->startsOn) : null;
        $signedExpiry = DateHelper::formatAs8601Zulu($this->expiresOn);
        $signedResource = match (true) {
            $this->blobName === null => 'c',
            $this->blobVersionId !== null => 'bv',
            $this->snapshot !== null => 'bs',
            default => 'b',
        };
        $signedIp = $this->ipRange !== null ? (string) $this->ipRange : null;
        $signedProtocol = $this->protocol?->value;
        $signedVersion = $this->version ?? ApiVersion::latestGA()->value;
        $signedSnapshotOrVersion = $this->snapshot ?? $this->blobVersionId;
        $canonicalizedResource = $this->getCanonicalizedResource($sharedKeyCredential->accountName);

        $stringToSign = [
            $this->permissions,
            $signedStart,
            $signedExpiry,
            $canonicalizedResource,
            $this->identifier,
            $signedIp,
            $signedProtocol,
            $signedVersion,
            $signedResource,
            $signedSnapshotOrVersion,
            $this->encryptionScope,
            $this->cacheControl,
            $this->contentDisposition,
            $this->contentEncoding,
            $this->contentLanguage,
            $this->contentType,
        ];
        $stringToSign = array_map(fn ($str) => urldecode($str ?? ''), $stringToSign);
        $stringToSign = implode("\n", $stringToSign);

        $signature = urlencode($sharedKeyCredential->computeHMACSHA256($stringToSign));

        return Query::build(array_filter([
            'st' => $signedStart,
            'se' => $signedExpiry,
            'sv' => $signedVersion,
            'sr' => $signedResource,
            'sip' => $signedIp,
            'sig' => $signature,
            'spr' => $signedProtocol,
            'sp' => $this->permissions,
            'si' => $this->identifier,
            'rscc' => $this->cacheControl,
            'rscd' => $this->contentDisposition,
            'rsce' => $this->contentEncoding,
            'rscl' => $this->contentLanguage,
            'rsct' => $this->contentType,
            'ses' => $this->encryptionScope,
        ], fn (?string $value) => $value !== null), false);
    }

    /**
     * Ensures the builder contains the minimum state required to sign a SAS.
     *
     * @throws UnableToGenerateSasException
     */
    private function validateState(): void
    {
        if (! isset($this->containerName) || $this->containerName === '') {
            throw new UnableToGenerateSasException('A container name is required to generate a SAS.');
        }

        if ($this->identifier !== null) {
            return;
        }

        if (! isset($this->expiresOn)) {
            throw new UnableToGenerateSasException(
                'An expiration time is required to generate a SAS without a stored access policy identifier.',
            );
        }

        if ($this->permissions === null) {
            throw new UnableToGenerateSasException(
                'Permissions are required to generate a SAS without a stored access policy identifier.',
            );
        }
    }

    private function getCanonicalizedResource(string $accountName): string
    {
        $resource = "/blob/$accountName/$this->containerName";

        if ($this->blobName !== null) {
            $resource .= "/$this->blobName";
        }

        return $resource;
    }
}
