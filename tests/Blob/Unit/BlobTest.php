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
                <Properties>
                    <Last-Modified>Sun, 27 Sep 2009 18:41:57 GMT</Last-Modified>
                    <Content-Length>1024</Content-Length>
                    <Content-Type>text/plain</Content-Type>
                    <Content-MD5>sQqNsWTgdUEFt6mb5y4/5Q==</Content-MD5>
                </Properties>
            </Blob>
            XML));

        self::assertSame('docs/readme.txt', $blob->name);
        self::assertSame(1024, $blob->properties->contentLength);
        self::assertSame('text/plain', $blob->properties->contentType);
    }
}
