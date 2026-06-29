<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\File\Share\Unit;

use AzureOss\Storage\File\Share\Sas\ShareSasPermissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShareSasPermissionsTest extends TestCase
{
    #[Test]
    public function to_string_works(): void
    {
        self::assertSame('', (string) new ShareSasPermissions);
        self::assertSame('rd', (string) new ShareSasPermissions(read: true, delete: true));
        self::assertSame('rcwdl', (string) new ShareSasPermissions(
            read: true,
            create: true,
            write: true,
            delete: true,
            list: true,
        ));
    }
}
