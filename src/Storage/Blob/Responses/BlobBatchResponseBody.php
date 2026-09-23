<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Responses;

use AzureOss\Storage\Blob\Exceptions\DeserializationException;
use GuzzleHttp\Psr7\Header;
use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class BlobBatchResponseBody
{
    /**
     * @param  array<int, ResponseInterface>  $responses  Sub-responses keyed by Content-ID.
     * @param  ResponseInterface|null  $rejection  The error part through which the service rejected the whole batch.
     */
    private function __construct(
        public readonly array $responses,
        public readonly ?ResponseInterface $rejection = null,
    ) {}

    /**
     * Parses a batch response to a batch of `$operationCount` operations.
     *
     * The service rejects a whole batch with a 202 whose only part is an error without a Content-ID,
     * so a single failed part is a rejection when it lacks a Content-ID or several operations were sent.
     *
     * @throws DeserializationException When the body is not a multipart batch response.
     */
    public static function fromResponse(ResponseInterface $response, int $operationCount): self
    {
        $parts = self::parseParts($response);

        if (count($parts) === 1 && $parts[0][1]->getStatusCode() >= 400 && ($parts[0][0] === null || $operationCount > 1)) {
            return new self([], $parts[0][1]);
        }

        $responses = [];

        foreach ($parts as [$contentId, $subResponse]) {
            $responses[$contentId ?? throw new DeserializationException('Blob batch response part has no Content-ID.')] = $subResponse;
        }

        return new self($responses);
    }

    /**
     * @return list<array{int|null, ResponseInterface}>
     */
    private static function parseParts(ResponseInterface $response): array
    {
        $boundary = self::boundary($response);
        $parts = explode("--{$boundary}", (string) $response->getBody());
        array_shift($parts);

        $parsed = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                break;
            }

            $parsed[] = self::parsePart($part);
        }

        return $parsed;
    }

    private static function boundary(ResponseInterface $response): string
    {
        $parameters = Header::parse($response->getHeaderLine('Content-Type'))[0] ?? [];
        $boundary = is_array($parameters) ? $parameters['boundary'] ?? '' : '';

        if (! is_string($boundary) || $boundary === '') {
            throw new DeserializationException('Blob batch response is not multipart.');
        }

        return trim($boundary, '"');
    }

    /**
     * @return array{int|null, ResponseInterface}
     */
    private static function parsePart(string $part): array
    {
        [$mimeHeaders, $httpMessage] = self::splitHeaders(ltrim($part));
        $contentId = preg_match('/^Content-ID:\s*(\d+)\s*$/mi', $mimeHeaders, $match) === 1 ? (int) $match[1] : null;

        $httpMessage = ltrim(str_ends_with($httpMessage, "\r\n") ? substr($httpMessage, 0, -2) : $httpMessage);
        [$head, $body] = self::splitHeaders($httpMessage);

        try {
            return [$contentId, Message::parseResponse(rtrim($head)."\r\n\r\n".$body)];
        } catch (\InvalidArgumentException $e) {
            throw new DeserializationException('Blob batch response part is not an HTTP response.', 0, $e);
        }
    }

    /**
     * @return array{string, string}
     */
    private static function splitHeaders(string $message): array
    {
        $sections = preg_split('/\r?\n\r?\n/', $message, 2);

        return $sections === false ? [$message, ''] : [$sections[0], $sections[1] ?? ''];
    }
}
