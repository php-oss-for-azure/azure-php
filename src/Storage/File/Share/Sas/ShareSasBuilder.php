<?php

declare(strict_types=1);

namespace AzureOss\Storage\File\Share\Sas;

use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Sas\SasIpRange;
use AzureOss\Storage\Common\Sas\SasProtocol;
use AzureOss\Storage\File\Share\Exceptions\UnableToGenerateSasException;
use GuzzleHttp\Psr7\Query;

/**
 * Builds an Azure Files service shared access signature (SAS).
 */
final class ShareSasBuilder
{
    private string $version;

    private string $shareName;

    private \DateTimeInterface $expiresOn;

    private ?string $filePath = null;

    private ?\DateTimeInterface $startsOn = null;

    private ?string $permissions = null;

    private ?string $identifier = null;

    private ?string $cacheControl = null;

    private ?string $contentDisposition = null;

    private ?string $contentEncoding = null;

    private ?string $contentLanguage = null;

    private ?string $contentType = null;

    private ?SasIpRange $ipRange = null;

    private ?SasProtocol $protocol = null;

    /** Creates an empty file share service SAS builder. */
    public static function new(): self
    {
        return new self;
    }

    /** Sets the share name included in the canonical signed resource. */
    public function setShareName(string $value): self
    {
        $this->shareName = $value;

        return $this;
    }

    /**
     * Sets the file path included in the canonical signed resource.
     *
     * The Azure Files service SAS model distinguishes only share (`sr=s`) and file (`sr=f`)
     * resources. Directory clients therefore stamp their path here using the same file-path
     * semantics that the Azure .NET SDK uses.
     */
    public function setFilePath(?string $value): self
    {
        $this->filePath = $value !== null ? self::normalizePath($value) : null;

        return $this;
    }

    /** Sets the instant at which the SAS expires. */
    public function setExpiresOn(\DateTimeInterface $value): self
    {
        $this->expiresOn = $value;

        return $this;
    }

    /** Sets the operations permitted by the SAS. */
    public function setPermissions(string|ShareSasPermissions|ShareFileSasPermissions $value): self
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

    /** Restricts requests to the specified source IP address or range. */
    public function setIPRange(SasIpRange $value): self
    {
        $this->ipRange = $value;

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

        $signedStart = $this->startsOn !== null ? self::formatAs8601Zulu($this->startsOn) : null;
        $signedExpiry = self::formatAs8601Zulu($this->expiresOn);
        $signedIp = $this->ipRange !== null ? (string) $this->ipRange : null;
        $signedProtocol = $this->protocol?->value;
        $signedVersion = $this->version ?? ApiVersion::latestGA()->value;
        $signedResource = $this->filePath === null ? 's' : 'f';
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
            $this->cacheControl,
            $this->contentDisposition,
            $this->contentEncoding,
            $this->contentLanguage,
            $this->contentType,
        ];
        $stringToSign = array_map(static fn (?string $str): string => urldecode($str ?? ''), $stringToSign);
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
        ], static fn (?string $value): bool => $value !== null), false);
    }

    /**
     * Ensures the builder contains the minimum state required to sign a SAS.
     *
     * @throws UnableToGenerateSasException
     */
    private function validateState(): void
    {
        if (! isset($this->shareName) || $this->shareName === '') {
            throw new UnableToGenerateSasException('A share name is required to generate a SAS.');
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
        $resource = "/file/$accountName/$this->shareName";

        if ($this->filePath !== null) {
            $resource .= "/$this->filePath";
        }

        return $resource;
    }

    private static function normalizePath(string $value): ?string
    {
        $path = trim(str_replace('\\', '/', $value), '/');

        return $path !== '' ? $path : null;
    }

    private static function formatAs8601Zulu(\DateTimeInterface $date): string
    {
        return \DateTime::createFromInterface($date)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }
}
