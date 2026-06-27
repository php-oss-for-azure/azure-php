<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Unit;

use AzureOss\Storage\Blob\Models\BlobPrefix;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobPrefixTest extends TestCase
{
    #[Test]
    public function it_deserializes_from_xml(): void
    {
        $prefix = BlobPrefix::fromXml(new \SimpleXMLElement('<BlobPrefix><Name>docs/</Name></BlobPrefix>'));

        self::assertSame('docs/', $prefix->name);
    }
}
