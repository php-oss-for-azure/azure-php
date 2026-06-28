<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

use AzureOss\Storage\Blob\Exceptions\DeserializationException;
use AzureOss\Storage\Blob\Helpers\DateHelper;
use AzureOss\Storage\Blob\Helpers\MetadataHelper;
use AzureOss\Storage\Common\Models\ETag;
use Psr\Http\Message\ResponseInterface;

/**
 * Represents Azure Storage blob container properties data.
 */
final class BlobContainerProperties
{
    /**
     * @param  array<string, string>  $metadata
     */
    private function __construct(
        public readonly \DateTimeInterface $lastModified,
        public readonly array $metadata,
        public readonly ?ETag $eTag = null,
        /** The time at which the container was soft-deleted. */
        public readonly ?\DateTimeInterface $deletedOn = null,
        /** Days remaining before the soft-deleted container is permanently removed. */
        public readonly ?int $remainingRetentionDays = null,
    ) {}

    public static function fromResponseHeaders(ResponseInterface $response): self
    {
        $lastModified = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC1123, $response->getHeaderLine('Last-Modified'));
        if ($lastModified === false) {
            throw new DeserializationException('Azure returned a malformed date.');
        }

        return new self(
            $lastModified,
            MetadataHelper::headersToMetadata($response->getHeaders()),
            $response->hasHeader('ETag') ? new ETag($response->getHeaderLine('ETag')) : null,
        );
    }

    /**
     * @param  array<string, string>  $metadata
     */
    public static function fromXml(\SimpleXMLElement $xml, array $metadata = []): self
    {
        $lastModified = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC1123, (string) $xml->{'Last-Modified'});
        if ($lastModified === false) {
            throw new DeserializationException('Azure returned a malformed date.');
        }

        $eTag = (string) $xml->Etag !== '' ? (string) $xml->Etag : (string) $xml->ETag;

        return new self(
            $lastModified,
            $metadata,
            $eTag !== '' ? new ETag($eTag) : null,
            (string) $xml->DeletedTime !== '' ? DateHelper::deserializeDateRfc1123Date((string) $xml->DeletedTime) : null,
            (string) $xml->RemainingRetentionDays !== '' ? (int) $xml->RemainingRetentionDays : null,
        );
    }
}
