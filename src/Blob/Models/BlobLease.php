<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

use AzureOss\Storage\Blob\Helpers\DateHelper;
use AzureOss\Storage\Common\Models\ETag;
use Psr\Http\Message\ResponseInterface;

final class BlobLease
{
    public function __construct(
        public readonly ?string $leaseId = null,
        public readonly ?ETag $eTag = null,
        public readonly ?\DateTimeInterface $lastModified = null,
        public readonly ?int $leaseTime = null,
    ) {}

    public static function fromResponse(ResponseInterface $response, ?string $leaseId = null): self
    {
        return new self(
            $response->hasHeader('x-ms-lease-id') ? $response->getHeaderLine('x-ms-lease-id') : $leaseId,
            $response->hasHeader('ETag') ? new ETag($response->getHeaderLine('ETag')) : null,
            $response->hasHeader('Last-Modified') ? DateHelper::deserializeDateRfc1123Date($response->getHeaderLine('Last-Modified')) : null,
            $response->hasHeader('x-ms-lease-time') ? (int) $response->getHeaderLine('x-ms-lease-time') : null,
        );
    }
}
