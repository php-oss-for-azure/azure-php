<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Unit;

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
                <Properties>
                    <Last-Modified>Sun, 27 Sep 2009 18:41:57 GMT</Last-Modified>
                    <Etag>0x8CB171DBEAD6A6B</Etag>
                </Properties>
            </Container>
            XML));

        self::assertSame('photos', $container->name);
        self::assertSame('2009-09-27T18:41:57+00:00', $container->properties->lastModified->format(\DateTimeInterface::ATOM));
    }
}
