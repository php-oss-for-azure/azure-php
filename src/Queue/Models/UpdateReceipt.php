<?php

declare(strict_types=1);

namespace AzureOss\Storage\Queue\Models;

use AzureOss\Storage\Queue\Exceptions\DeserializationException;
use Psr\Http\Message\ResponseInterface;

final class UpdateReceipt
{
    private function __construct(
        public readonly string $popReceipt,
        public readonly \DateTimeInterface $timeNextVisible,
    ) {}

    public static function fromXml(\SimpleXMLElement $xml): self
    {
        $timeNextVisible = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC1123, (string) $xml->TimeNextVisible);
        if ($timeNextVisible === false) {
            throw new DeserializationException('Azure returned a malformed date.');
        }

        return new self(
            (string) $xml->PopReceipt,
            $timeNextVisible,
        );
    }

    public static function fromResponseHeaders(ResponseInterface $response): self
    {
        $popReceipt = $response->getHeaderLine('x-ms-popreceipt');
        if ($popReceipt === '') {
            throw new DeserializationException('Azure returned a missing pop receipt.');
        }

        $time = $response->getHeaderLine('x-ms-time-next-visible');
        $timeNextVisible = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC1123, $time);
        if ($timeNextVisible === false) {
            throw new DeserializationException('Azure returned a malformed date.');
        }

        return new self($popReceipt, $timeNextVisible);
    }
}
