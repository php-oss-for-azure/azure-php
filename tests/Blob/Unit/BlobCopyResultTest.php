<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Unit;

use AzureOss\Storage\Blob\Models\BlobCopyResult;
use AzureOss\Storage\Blob\Models\CopyStatus;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobCopyResultTest extends TestCase
{
    #[Test]
    public function it_deserializes_from_response_headers(): void
    {
        $result = BlobCopyResult::fromResponse(new Response(202, [
            'x-ms-copy-id' => 'copy-id',
            'x-ms-copy-status' => 'pending',
        ]));

        self::assertSame('copy-id', $result->copyId);
        self::assertSame(CopyStatus::PENDING, $result->copyStatus);
    }
}
