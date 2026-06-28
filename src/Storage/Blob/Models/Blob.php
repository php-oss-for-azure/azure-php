<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

/**
 * Represents Azure Storage blob data.
 */
final class Blob
{
    /**
     * @param  array<string, string>|null  $metadata  User-defined metadata, or null when metadata was not returned.
     * @param  array<string, string>|null  $tags  Blob index tags, or null when no tags were returned.
     */
    private function __construct(
        /** The blob name. */
        public readonly string $name,
        /** The blob system properties returned by the service. */
        public readonly BlobProperties $properties,
        /** The snapshot timestamp for this blob snapshot when snapshots are included. */
        public readonly ?string $snapshot = null,
        /** Indicates whether this blob or blob version is soft-deleted. */
        public readonly bool $deleted = false,
        /** The version identifier for this blob when versions are included. */
        public readonly ?string $versionId = null,
        /** Indicates whether this version is the current version of the blob. */
        public readonly ?bool $isLatestVersion = null,
        /** @var array<string, string>|null User-defined metadata, or null when metadata was not returned. */
        public readonly ?array $metadata = null,
        /** @var array<string, string>|null Blob index tags, or null when tags were not returned. */
        public readonly ?array $tags = null,
        /** Indicates whether the listed item only represents versions for a deleted base blob. */
        public readonly ?bool $hasVersionsOnly = null,
    ) {}

    public static function fromXml(\SimpleXMLElement $xml): self
    {
        $metadata = isset($xml->Metadata) ? self::deserializeDictionary($xml->Metadata) : null;
        $tags = null;

        if (isset($xml->Tags->TagSet)) {
            $tags = [];
            foreach ($xml->Tags->TagSet->children() as $tag) {
                $tags[(string) $tag->Key] = (string) $tag->Value;
            }
        }

        return new self(
            (string) $xml->Name,
            BlobProperties::fromXml($xml->Properties, $metadata),
            self::deserializeNullableString($xml->Snapshot),
            self::deserializeBool($xml->Deleted) ?? false,
            self::deserializeNullableString($xml->VersionId),
            self::deserializeBool($xml->IsCurrentVersion),
            $metadata,
            $tags,
            self::deserializeBool($xml->HasVersionsOnly),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function deserializeDictionary(\SimpleXMLElement $xml): array
    {
        $values = [];
        foreach ($xml->children() as $key => $value) {
            $values[$key] = (string) $value;
        }

        return $values;
    }

    private static function deserializeNullableString(\SimpleXMLElement $xml): ?string
    {
        return (string) $xml !== '' ? (string) $xml : null;
    }

    private static function deserializeBool(\SimpleXMLElement $xml): ?bool
    {
        if ((string) $xml === '') {
            return null;
        }

        return filter_var((string) $xml, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
