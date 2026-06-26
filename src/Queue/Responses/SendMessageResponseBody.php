<?php

declare(strict_types=1);

namespace AzureOss\Storage\Queue\Responses;

use AzureOss\Storage\Queue\Exceptions\DeserializationException;
use AzureOss\Storage\Queue\Models\SendReceipt;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class SendMessageResponseBody
{
    private function __construct(
        public readonly SendReceipt $receipt,
    ) {}

    public static function fromResponse(ResponseInterface $response): SendReceipt
    {
        return self::fromXml(new \SimpleXMLElement($response->getBody()->getContents()))->receipt;
    }

    public static function fromXml(\SimpleXMLElement $xml): self
    {
        $queueMessage = $xml->QueueMessage;
        if (! isset($queueMessage)) {
            throw new DeserializationException('Azure returned a malformed response.');
        }

        return new self(SendReceipt::fromXml($queueMessage));
    }
}
