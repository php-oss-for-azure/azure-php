<?php

declare(strict_types=1);

namespace AzureOss\Storage\Queue\Responses;

use AzureOss\Storage\Queue\Exceptions\DeserializationException;
use AzureOss\Storage\Queue\Models\UpdateReceipt;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class UpdateMessageResponseBody
{
    private function __construct(
        public readonly UpdateReceipt $receipt,
    ) {}

    public static function fromResponse(ResponseInterface $response): UpdateReceipt
    {
        $contents = $response->getBody()->getContents();
        if ($contents !== '') {
            return self::fromXml(new \SimpleXMLElement($contents))->receipt;
        }

        return UpdateReceipt::fromResponseHeaders($response);
    }

    public static function fromXml(\SimpleXMLElement $xml): self
    {
        $queueMessage = $xml->QueueMessage;
        if (! isset($queueMessage)) {
            throw new DeserializationException('Azure returned a malformed response.');
        }

        return new self(UpdateReceipt::fromXml($queueMessage));
    }
}
