<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Unit;

use AzureOss\Storage\Blob\Models\BlobContainerProperties;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobContainerPropertiesTest extends TestCase
{
    #[Test]
    public function it_deserializes_from_response_headers(): void
    {
        $properties = BlobContainerProperties::fromResponseHeaders(new Response(200, [
            'Last-Modified' => 'Sun, 27 Sep 2009 18:41:57 GMT',
            'x-ms-meta-purpose' => 'backups',
        ]));

        self::assertSame('2009-09-27T18:41:57+00:00', $properties->lastModified->format(\DateTimeInterface::ATOM));
        self::assertSame(['purpose' => 'backups'], $properties->metadata);
    }

    #[Test]
    public function it_deserializes_from_xml(): void
    {
        $properties = BlobContainerProperties::fromXml(new \SimpleXMLElement(<<<'XML'
            <Properties>
                <Last-Modified>Sun, 27 Sep 2009 18:41:57 GMT</Last-Modified>
                <Etag>0x8CB171DBEAD6A6B</Etag>
            </Properties>
            XML));

        self::assertSame('2009-09-27T18:41:57+00:00', $properties->lastModified->format(\DateTimeInterface::ATOM));
        self::assertSame([], $properties->metadata);
    }
}
