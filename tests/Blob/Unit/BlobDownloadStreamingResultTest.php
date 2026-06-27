<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Unit;

use AzureOss\Storage\Blob\Models\BlobDownloadStreamingResult;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobDownloadStreamingResultTest extends TestCase
{
    #[Test]
    public function it_deserializes_from_response_headers(): void
    {
        $result = BlobDownloadStreamingResult::fromResponse(new Response(200, [
            'Last-Modified' => 'Sun, 27 Sep 2009 18:41:57 GMT',
            'Content-Length' => '11',
            'Content-Type' => 'text/plain',
            'Content-MD5' => 'sQqNsWTgdUEFt6mb5y4/5Q==',
        ], 'hello world'));

        self::assertSame('hello world', $result->content->getContents());
        self::assertSame(11, $result->properties->contentLength);
        self::assertSame('text/plain', $result->properties->contentType);
    }
}
