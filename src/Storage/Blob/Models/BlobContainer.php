<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

/**
 * Represents Azure Storage blob container data.
 */
final class BlobContainer
{
    private function __construct(
        public readonly string $name,
        public readonly BlobContainerProperties $properties,
        /** The service-generated version that uniquely identifies a soft-deleted container. */
        public readonly ?string $versionId = null,
        /** Indicates whether this container is soft-deleted and eligible for restoration. */
        public readonly bool $isDeleted = false,
    ) {}

    public static function fromXml(\SimpleXMLElement $xml): self
    {
        return new self(
            (string) $xml->Name,
            BlobContainerProperties::fromXml($xml->Properties, self::deserializeMetadata($xml)),
            (string) $xml->Version !== '' ? (string) $xml->Version : null,
            filter_var((string) $xml->Deleted, FILTER_VALIDATE_BOOL),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function deserializeMetadata(\SimpleXMLElement $xml): array
    {
        $metadata = [];
        if (! isset($xml->Metadata)) {
            return $metadata;
        }

        foreach ($xml->Metadata->children() as $key => $value) {
            $metadata[$key] = (string) $value;
        }

        return $metadata;
    }
}
