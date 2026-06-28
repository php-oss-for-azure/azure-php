<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

use AzureOss\Storage\Blob\Helpers\DateHelper;
use AzureOss\Storage\Blob\Helpers\HashHelper;
use AzureOss\Storage\Blob\Helpers\MetadataHelper;
use AzureOss\Storage\Common\Models\ETag;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Represents Azure Storage blob properties data.
 */
final class BlobProperties
{
    /**
     * @param  array<string, string>|null  $metadata
     */
    private function __construct(
        public readonly ?\DateTimeInterface $lastModified,
        public readonly int $contentLength,
        public readonly string $contentType,
        public readonly ?string $contentMD5,
        /** @var array<string, string>|null User-defined metadata, or null when metadata was not returned. */
        public readonly ?array $metadata,
        public readonly ?string $copyId = null,
        public readonly ?UriInterface $copySource = null,
        public readonly ?CopyStatus $copyStatus = null,
        public readonly ?string $copyStatusDescription = null,
        public readonly ?\DateTimeInterface $copyCompletionTime = null,
        public readonly string $cacheControl = '',
        public readonly string $contentDisposition = '',
        public readonly string $contentLanguage = '',
        public readonly string $contentEncoding = '',
        public readonly ?ETag $eTag = null,
        /** The time at which the blob or snapshot was soft-deleted. */
        public readonly ?\DateTimeInterface $deletedOn = null,
        /** Days remaining before the soft-deleted blob or snapshot is permanently removed. */
        public readonly ?int $remainingRetentionDays = null,
    ) {}

    public static function fromResponseHeaders(ResponseInterface $response): self
    {
        return new self(
            DateHelper::deserializeDateRfc1123Date($response->getHeaderLine('Last-Modified')),
            $response->getHeaderLine('x-encoded-content-length') !== '' ? (int) $response->getHeaderLine('x-encoded-content-length') : (int) $response->getHeaderLine('Content-Length'),
            $response->getHeaderLine('Content-Type'),
            HashHelper::deserializeMd5($response->getHeaderLine('Content-MD5')),
            MetadataHelper::headersToMetadata($response->getHeaders()),
            $response->hasHeader('x-ms-copy-id') ? $response->getHeaderLine('x-ms-copy-id') : null,
            $response->hasHeader('x-ms-copy-source') ? new Uri($response->getHeaderLine('x-ms-copy-source')) : null,
            $response->hasHeader('x-ms-copy-status') ? CopyStatus::from($response->getHeaderLine('x-ms-copy-status')) : null,
            $response->hasHeader('x-ms-copy-status-description') ? $response->getHeaderLine('x-ms-copy-status-description') : null,
            $response->hasHeader('x-ms-copy-completion-time') ? DateHelper::deserializeDateRfc1123Date($response->getHeaderLine('x-ms-copy-completion-time')) : null,
            $response->getHeaderLine('Cache-Control'),
            $response->getHeaderLine('Content-Disposition'),
            $response->getHeaderLine('Content-Language'),
            $response->getHeaderLine('x-encoded-content-encoding'),
            $response->hasHeader('ETag') ? new ETag($response->getHeaderLine('ETag')) : null,
            $response->hasHeader('x-ms-deleted-time') ? DateHelper::deserializeDateRfc1123Date($response->getHeaderLine('x-ms-deleted-time')) : null,
            $response->hasHeader('x-ms-remaining-retention-days') ? (int) $response->getHeaderLine('x-ms-remaining-retention-days') : null,
        );
    }

    /**
     * @param  array<string, string>|null  $metadata
     */
    public static function fromXml(\SimpleXMLElement $xml, ?array $metadata = null): self
    {
        $eTag = (string) $xml->Etag !== '' ? (string) $xml->Etag : (string) $xml->ETag;

        return new self(
            (string) $xml->{'Last-Modified'} !== '' ? DateHelper::deserializeDateRfc1123Date((string) $xml->{'Last-Modified'}) : null,
            (int) $xml->{'Content-Length'},
            (string) $xml->{'Content-Type'},
            HashHelper::deserializeMd5((string) $xml->{'Content-MD5'}),
            $metadata,
            (string) $xml->CopyId !== '' ? (string) $xml->CopyId : null,
            (string) $xml->CopySource !== '' ? new Uri((string) $xml->CopySource) : null,
            (string) $xml->CopyStatus !== '' ? CopyStatus::tryFrom((string) $xml->CopyStatus) : null,
            (string) $xml->CopyStatusDescription !== '' ? (string) $xml->CopyStatusDescription : null,
            (string) $xml->CopyCompletionTime !== '' ? DateHelper::deserializeDateRfc1123Date((string) $xml->CopyCompletionTime) : null,
            (string) $xml->{'Cache-Control'},
            (string) $xml->{'Content-Disposition'},
            (string) $xml->{'Content-Language'},
            (string) $xml->{'Content-Encoding'},
            $eTag !== '' ? new ETag($eTag) : null,
            (string) $xml->DeletedTime !== '' ? DateHelper::deserializeDateRfc1123Date((string) $xml->DeletedTime) : null,
            (string) $xml->RemainingRetentionDays !== '' ? (int) $xml->RemainingRetentionDays : null,
        );
    }
}
