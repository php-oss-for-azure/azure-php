<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\File\Share\Feature;

use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\File\Share\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\File\Share\Sas\ShareFileSasPermissions;
use AzureOss\Storage\File\Share\Sas\ShareSasBuilder;
use AzureOss\Storage\File\Share\ShareFileClient;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

final class ShareFileClientTest extends TestCase
{
    #[Test]
    public function can_generate_sas_uri_works(): void
    {
        self::assertFalse((new ShareFileClient(new Uri('https://account.file.core.windows.net/share/path/file.txt')))->canGenerateSasUri());
        self::assertTrue((new ShareFileClient(
            new Uri('https://account.file.core.windows.net/share/path/file.txt'),
            new StorageSharedKeyCredential('noop', 'bm9vcA=='),
        ))->canGenerateSasUri());
    }

    #[Test]
    public function generate_sas_uri_stamps_the_file_resource_and_preserves_existing_query(): void
    {
        $file = new ShareFileClient(
            new Uri('https://account.file.core.windows.net/share/path/file.txt?custom=value'),
            new StorageSharedKeyCredential('account', base64_encode(str_repeat('x', 32))),
        );

        $sas = $file->generateSasUri(
            ShareSasBuilder::new()
                ->setPermissions(new ShareFileSasPermissions(read: true))
                ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z')),
        );
        $query = $this->query($sas);

        self::assertSame('f', $query['sr'] ?? null);
        self::assertSame('r', $query['sp'] ?? null);
        self::assertSame('value', $query['custom'] ?? null);
    }

    #[Test]
    public function generate_sas_uri_throws_when_there_is_no_shared_key_credential(): void
    {
        $this->expectException(UnableToGenerateSasException::class);

        (new ShareFileClient(new Uri('https://account.file.core.windows.net/share/path/file.txt')))
            ->generateSasUri(ShareSasBuilder::new());
    }

    /**
     * @return array<string, string>
     */
    private function query(UriInterface $uri): array
    {
        /** @var array<string, string> $query */
        $query = Query::parse($uri->getQuery());

        return $query;
    }
}
