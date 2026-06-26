<?php

declare(strict_types=1);

namespace AzureOss\Storage\Queue\Models;

use AzureOss\Storage\Queue\Exceptions\DeserializationException;

final class QueueMessage
{
    private function __construct(
        public readonly string $messageId,
        public readonly string $popReceipt,
        public readonly string $messageText,
        public readonly \DateTimeInterface $insertionTime,
        public readonly \DateTimeInterface $expirationTime,
        public readonly int $dequeueCount,
        public readonly \DateTimeInterface $timeNextVisible,
    ) {}

    public static function fromXml(\SimpleXMLElement $xml): self
    {
        $insertionTime = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC1123, (string) $xml->InsertionTime);
        if ($insertionTime === false) {
            throw new DeserializationException('Azure returned a malformed date.');
        }

        $expirationTime = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC1123, (string) $xml->ExpirationTime);
        if ($expirationTime === false) {
            throw new DeserializationException('Azure returned a malformed date.');
        }

        $timeNextVisible = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC1123, (string) $xml->TimeNextVisible);
        if ($timeNextVisible === false) {
            throw new DeserializationException('Azure returned a malformed date.');
        }

        return new self(
            (string) $xml->MessageId,
            (string) $xml->PopReceipt,
            (string) $xml->MessageText,
            $insertionTime,
            $expirationTime,
            (int) $xml->DequeueCount,
            $timeNextVisible,
        );
    }
}
