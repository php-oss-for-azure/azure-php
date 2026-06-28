<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Blob\Unit;

use AzureOss\Storage\Blob\Models\BlobContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobContainerTest extends TestCase
{
    #[Test]
    public function it_deserializes_from_xml(): void
    {
        $container = BlobContainer::fromXml(new \SimpleXMLElement(<<<'XML'
            <Container>
                <Name>photos</Name>
                <Version>01D9A8BY7Q4Y4J</Version>
                <Deleted>true</Deleted>
                <Properties>
                    <Last-Modified>Sun, 27 Sep 2009 18:41:57 GMT</Last-Modified>
                    <Etag>0x8CB171DBEAD6A6B</Etag>
                    <DeletedTime>Mon, 28 Sep 2009 18:41:57 GMT</DeletedTime>
                    <RemainingRetentionDays>5</RemainingRetentionDays>
                </Properties>
                <Metadata><purpose>photos</purpose></Metadata>
            </Container>
            XML));

        self::assertSame('photos', $container->name);
        self::assertSame('2009-09-27T18:41:57+00:00', $container->properties->lastModified->format(\DateTimeInterface::ATOM));
        self::assertSame('01D9A8BY7Q4Y4J', $container->versionId);
        self::assertTrue($container->isDeleted);
        self::assertSame('2009-09-28T18:41:57+00:00', $container->properties->deletedOn?->format(\DateTimeInterface::ATOM));
        self::assertSame(5, $container->properties->remainingRetentionDays);
        self::assertSame(['purpose' => 'photos'], $container->properties->metadata);
    }
}
