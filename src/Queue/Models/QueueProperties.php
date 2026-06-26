<?php

declare(strict_types=1);

namespace AzureOss\Storage\Queue\Models;

use AzureOss\Storage\Queue\Exceptions\DeserializationException;
use AzureOss\Storage\Queue\Helpers\MetadataHelper;
use Psr\Http\Message\ResponseInterface;

final class QueueProperties
{
    /**
     * @param  array<string>  $metadata
     */
    private function __construct(
        public readonly ?\DateTimeInterface $lastModified,
        public readonly string $etag,
        public readonly int $approximateMessagesCount,
        public readonly array $metadata,
    ) {}

    public static function fromResponseHeaders(ResponseInterface $response): self
    {
        $lastModified = self::tryParseHttpDate($response->getHeaderLine('Last-Modified'));

        $etag = $response->getHeaderLine('ETag');
        $count = $response->getHeaderLine('x-ms-approximate-messages-count');

        return new self(
            $lastModified,
            $etag,
            $count === '' ? 0 : (int) $count,
            MetadataHelper::headersToMetadata($response->getHeaders()),
        );
    }

    private static function tryParseHttpDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC1123, $value);
        if ($dt !== false) {
            return $dt;
        }

        // Common HTTP date format: "D, d M Y H:i:s GMT"
        $dt = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s \\G\\M\\T', $value);
        if ($dt !== false) {
            return $dt->setTimezone(new \DateTimeZone('UTC'));
        }

        throw new DeserializationException('Azure returned a malformed date.');
    }
}
