<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Common\Unit;

use AzureOss\Storage\Common\Helpers\StorageUriParserHelper;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StorageUriParserHelperTest extends TestCase
{
    #[Test]
    public function development_uri_works(): void
    {
        self::assertTrue(StorageUriParserHelper::isDevelopmentUri(
            new Uri('http://127.0.0.1:10000/devstoreaccount1/container/blob'),
        ));
        self::assertTrue(StorageUriParserHelper::isDevelopmentUri(
            new Uri('http://azurite:8080/devstoreaccount1/queue'),
        ));
        self::assertFalse(StorageUriParserHelper::isDevelopmentUri(
            new Uri('http://127.0.0.1:10000/another-account/container/blob'),
        ));
        self::assertFalse(StorageUriParserHelper::isDevelopmentUri(
            new Uri('https://account.blob.core.windows.net/container/blob'),
        ));
    }
}
