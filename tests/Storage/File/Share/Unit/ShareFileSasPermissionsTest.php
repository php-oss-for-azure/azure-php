<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\File\Share\Unit;

use AzureOss\Storage\File\Share\Sas\ShareFileSasPermissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShareFileSasPermissionsTest extends TestCase
{
    #[Test]
    public function to_string_works(): void
    {
        self::assertSame('', (string) new ShareFileSasPermissions);
        self::assertSame('rd', (string) new ShareFileSasPermissions(read: true, delete: true));
        self::assertSame('rcwd', (string) new ShareFileSasPermissions(
            read: true,
            create: true,
            write: true,
            delete: true,
        ));
    }
}
