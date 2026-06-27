<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Queue\Unit;

use AzureOss\Storage\Queue\Models\QueueProperties;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueuePropertiesTest extends TestCase
{
    #[Test]
    public function it_deserializes_from_response_headers(): void
    {
        $properties = QueueProperties::fromResponseHeaders(new Response(200, [
            'Last-Modified' => 'Sun, 27 Sep 2009 18:41:57 GMT',
            'ETag' => '"0x8D101F7E4B662C4"',
            'x-ms-approximate-messages-count' => '42',
            'x-ms-meta-owner' => 'storage-team',
        ]));

        self::assertSame('2009-09-27T18:41:57+00:00', $properties->lastModified?->format(\DateTimeInterface::ATOM));
        self::assertSame('"0x8D101F7E4B662C4"', $properties->etag);
        self::assertSame(42, $properties->approximateMessagesCount);
        self::assertSame(['owner' => 'storage-team'], $properties->metadata);
    }
}
