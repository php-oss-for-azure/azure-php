<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Unit;

use AzureOss\Storage\Blob\Models\Blob;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobTest extends TestCase
{
    #[Test]
    public function it_deserializes_from_xml(): void
    {
        $blob = Blob::fromXml(new \SimpleXMLElement(<<<'XML'
            <Blob>
                <Name>docs/readme.txt</Name>
                <Snapshot>2025-01-01T11:00:00.0000000Z</Snapshot>
                <VersionId>2025-01-01T12:00:00.0000000Z</VersionId>
                <IsCurrentVersion>true</IsCurrentVersion>
                <Deleted>true</Deleted>
                <HasVersionsOnly>false</HasVersionsOnly>
                <Properties>
                    <Last-Modified>Sun, 27 Sep 2009 18:41:57 GMT</Last-Modified>
                    <Content-Length>1024</Content-Length>
                    <Content-Type>text/plain</Content-Type>
                    <Content-MD5>sQqNsWTgdUEFt6mb5y4/5Q==</Content-MD5>
                </Properties>
                <Metadata>
                    <owner>storage-team</owner>
                    <environment>production</environment>
                </Metadata>
                <Tags>
                    <TagSet>
                        <Tag><Key>project</Key><Value>blue</Value></Tag>
                        <Tag><Key>env</Key><Value>test</Value></Tag>
                    </TagSet>
                </Tags>
            </Blob>
            XML));

        self::assertSame('docs/readme.txt', $blob->name);
        self::assertSame(1024, $blob->properties->contentLength);
        self::assertSame('text/plain', $blob->properties->contentType);
        self::assertSame('2025-01-01T11:00:00.0000000Z', $blob->snapshot);
        self::assertTrue($blob->deleted);
        self::assertSame('2025-01-01T12:00:00.0000000Z', $blob->versionId);
        self::assertTrue($blob->isLatestVersion);
        self::assertFalse($blob->hasVersionsOnly);
        self::assertSame(['owner' => 'storage-team', 'environment' => 'production'], $blob->metadata);
        self::assertSame($blob->metadata, $blob->properties->metadata);
        self::assertSame(['project' => 'blue', 'env' => 'test'], $blob->tags);
    }

    #[Test]
    public function it_uses_defaults_when_optional_xml_elements_are_absent(): void
    {
        $blob = Blob::fromXml(new \SimpleXMLElement(<<<'XML'
            <Blob>
                <Name>uncommitted.bin</Name>
                <Properties>
                    <Content-Length>0</Content-Length>
                </Properties>
            </Blob>
            XML));

        self::assertFalse($blob->deleted);
        self::assertNull($blob->snapshot);
        self::assertNull($blob->versionId);
        self::assertNull($blob->isLatestVersion);
        self::assertNull($blob->hasVersionsOnly);
        self::assertNull($blob->metadata);
        self::assertNull($blob->tags);
        self::assertNull($blob->properties->lastModified);
        self::assertNull($blob->properties->metadata);
    }
}
