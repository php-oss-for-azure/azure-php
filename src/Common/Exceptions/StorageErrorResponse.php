<?php

declare(strict_types=1);

namespace AzureOss\Storage\Common\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class StorageErrorResponse
{
    private function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly ?string $requestId = null,
        public readonly ?int $statusCode = null,
    ) {}

    public static function fromResponse(ResponseInterface $response): ?self
    {
        $requestIdHeader = $response->getHeaderLine('x-ms-request-id');
        $requestId = $requestIdHeader !== '' ? $requestIdHeader : null;
        $statusCode = $response->getStatusCode();
        $content = $response->getBody()->getContents();

        if ($content !== '') {
            return self::fromXml(new \SimpleXMLElement($content), $requestId, $statusCode);
        }

        $code = $response->getHeaderLine('x-ms-error-code');
        if ($code === '') {
            return null;
        }

        return new self(
            $code,
            $response->getReasonPhrase() !== '' ? $response->getReasonPhrase() : $code,
            $requestId,
            $statusCode,
        );
    }

    public static function fromXml(\SimpleXMLElement $xml, ?string $requestId = null, ?int $statusCode = null): self
    {
        return new self(
            (string) $xml->Code,
            (string) $xml->Message,
            $requestId,
            $statusCode,
        );
    }
}
