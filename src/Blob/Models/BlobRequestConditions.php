<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

use AzureOss\Storage\Common\Models\ETag;

final class BlobRequestConditions
{
    public function __construct(
        public ?ETag $ifMatch = null,
        public ?\DateTimeInterface $ifModifiedSince = null,
        public ?ETag $ifNoneMatch = null,
        public ?\DateTimeInterface $ifUnmodifiedSince = null,
        public ?string $leaseId = null,
    ) {}

    /**
     * @internal
     *
     * @return array<string, string>
     */
    public function toHeaders(
        bool $ifMatch = true,
        bool $ifModifiedSince = true,
        bool $ifNoneMatch = true,
        bool $ifUnmodifiedSince = true,
        bool $leaseId = true,
        string $prefix = '',
    ): array {
        $headerNames = $prefix === '' ? [
            'ifMatch' => 'If-Match',
            'ifModifiedSince' => 'If-Modified-Since',
            'ifNoneMatch' => 'If-None-Match',
            'ifUnmodifiedSince' => 'If-Unmodified-Since',
            'leaseId' => 'x-ms-lease-id',
        ] : [
            'ifMatch' => $prefix.'if-match',
            'ifModifiedSince' => $prefix.'if-modified-since',
            'ifNoneMatch' => $prefix.'if-none-match',
            'ifUnmodifiedSince' => $prefix.'if-unmodified-since',
            'leaseId' => $prefix.'lease-id',
        ];

        return array_filter([
            $headerNames['ifMatch'] => $ifMatch && $this->ifMatch !== null ? (string) $this->ifMatch : null,
            $headerNames['ifModifiedSince'] => $ifModifiedSince && $this->ifModifiedSince !== null ? self::formatDate($this->ifModifiedSince) : null,
            $headerNames['ifNoneMatch'] => $ifNoneMatch && $this->ifNoneMatch !== null ? (string) $this->ifNoneMatch : null,
            $headerNames['ifUnmodifiedSince'] => $ifUnmodifiedSince && $this->ifUnmodifiedSince !== null ? self::formatDate($this->ifUnmodifiedSince) : null,
            $headerNames['leaseId'] => $leaseId ? $this->leaseId : null,
        ], fn (?string $value): bool => $value !== null);
    }

    private static function formatDate(\DateTimeInterface $date): string
    {
        return \DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new \DateTimeZone('GMT'))
            ->format('D, d M Y H:i:s \G\M\T');
    }
}
