<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\File\Share\Feature;

use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\File\Share\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\File\Share\Sas\ShareFileSasPermissions;
use AzureOss\Storage\File\Share\Sas\ShareSasBuilder;
use AzureOss\Storage\File\Share\ShareDirectoryClient;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

final class ShareDirectoryClientTest extends TestCase
{
    #[Test]
    public function create_child_directory_client_works(): void
    {
        $directory = new ShareDirectoryClient(new Uri('https://account.file.core.windows.net/share/nested'));
        $child = $directory->getDirectoryClient('child/path');

        self::assertSame('https://account.file.core.windows.net/share/nested/child/path', (string) $child->uri);
    }

    #[Test]
    public function create_file_client_works(): void
    {
        $directory = new ShareDirectoryClient(new Uri('https://account.file.core.windows.net/share/nested'));
        $file = $directory->getFileClient('file.txt');

        self::assertSame('https://account.file.core.windows.net/share/nested/file.txt', (string) $file->uri);
    }

    #[Test]
    public function can_generate_sas_uri_works(): void
    {
        self::assertFalse((new ShareDirectoryClient(new Uri('https://account.file.core.windows.net/share/nested')))->canGenerateSasUri());
        self::assertTrue((new ShareDirectoryClient(
            new Uri('https://account.file.core.windows.net/share/nested'),
            new StorageSharedKeyCredential('noop', 'bm9vcA=='),
        ))->canGenerateSasUri());
    }

    #[Test]
    public function generate_sas_uri_signs_the_directory_path_as_a_file_resource(): void
    {
        $directory = new ShareDirectoryClient(
            new Uri('https://account.file.core.windows.net/share/nested/path?custom=value'),
            new StorageSharedKeyCredential('account', base64_encode(str_repeat('x', 32))),
        );

        $sas = $directory->generateSasUri(
            ShareSasBuilder::new()
                ->setPermissions(new ShareFileSasPermissions(read: true))
                ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z')),
        );
        $query = $this->query($sas);

        self::assertSame('f', $query['sr'] ?? null);
        self::assertSame('r', $query['sp'] ?? null);
        self::assertSame('value', $query['custom'] ?? null);
        self::assertArrayNotHasKey('sdd', $query);
    }

    #[Test]
    public function generate_sas_uri_throws_when_there_is_no_shared_key_credential(): void
    {
        $this->expectException(UnableToGenerateSasException::class);

        (new ShareDirectoryClient(new Uri('https://account.file.core.windows.net/share/nested')))
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
