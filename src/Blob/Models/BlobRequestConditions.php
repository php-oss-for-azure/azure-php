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
     * @return array{
     *     If-Match?: string,
     *     If-Modified-Since?: string,
     *     If-None-Match?: string,
     *     If-Unmodified-Since?: string,
     *     x-ms-lease-id?: string
     * }
     */
    public function toHeaders(): array
    {
        return array_filter([
            'If-Match' => $this->ifMatch !== null ? (string) $this->ifMatch : null,
            'If-Modified-Since' => $this->ifModifiedSince !== null ? self::formatDate($this->ifModifiedSince) : null,
            'If-None-Match' => $this->ifNoneMatch !== null ? (string) $this->ifNoneMatch : null,
            'If-Unmodified-Since' => $this->ifUnmodifiedSince !== null ? self::formatDate($this->ifUnmodifiedSince) : null,
            'x-ms-lease-id' => $this->leaseId,
        ], fn ($value) => $value !== null);
    }

    /**
     * @internal
     *
     * @return array{
     *     x-ms-source-if-match?: string,
     *     x-ms-source-if-modified-since?: string,
     *     x-ms-source-if-none-match?: string,
     *     x-ms-source-if-unmodified-since?: string,
     *     x-ms-source-lease-id?: string
     * }
     */
    public function toSourceHeaders(): array
    {
        return array_filter([
            'x-ms-source-if-match' => $this->ifMatch !== null ? (string) $this->ifMatch : null,
            'x-ms-source-if-modified-since' => $this->ifModifiedSince !== null ? self::formatDate($this->ifModifiedSince) : null,
            'x-ms-source-if-none-match' => $this->ifNoneMatch !== null ? (string) $this->ifNoneMatch : null,
            'x-ms-source-if-unmodified-since' => $this->ifUnmodifiedSince !== null ? self::formatDate($this->ifUnmodifiedSince) : null,
            'x-ms-source-lease-id' => $this->leaseId,
        ], fn ($value) => $value !== null);
    }

    private static function formatDate(\DateTimeInterface $date): string
    {
        return \DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new \DateTimeZone('GMT'))
            ->format('D, d M Y H:i:s \G\M\T');
    }
}
