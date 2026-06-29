<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\File\Share\Integration;

use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\File\Share\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\File\Share\Sas\ShareFileSasPermissions;
use AzureOss\Storage\File\Share\Sas\ShareSasBuilder;
use AzureOss\Storage\File\Share\Sas\ShareSasPermissions;
use AzureOss\Storage\File\Share\ShareClient;
use AzureOss\Storage\File\Share\ShareFileClient;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

final class ShareClientTest extends TestCase
{
    #[Test]
    public function create_directory_client_works(): void
    {
        $share = new ShareClient(new Uri('https://account.file.core.windows.net/share'));
        $directory = $share->getDirectoryClient('nested/path');

        self::assertSame('https://account.file.core.windows.net/share/nested/path', (string) $directory->uri);
    }

    #[Test]
    public function create_file_client_works_with_leading_slash(): void
    {
        $share = new ShareClient(new Uri('https://account.file.core.windows.net/share'));
        $file = $share->getFileClient('/nested/file.txt');

        self::assertSame('https://account.file.core.windows.net/share/nested/file.txt', (string) $file->uri);
    }

    #[Test]
    public function can_generate_sas_uri_works(): void
    {
        self::assertFalse((new ShareClient(new Uri('https://account.file.core.windows.net/share')))->canGenerateSasUri());
        self::assertTrue((new ShareClient(
            new Uri('https://account.file.core.windows.net/share'),
            new StorageSharedKeyCredential('noop', 'bm9vcA=='),
        ))->canGenerateSasUri());
    }

    #[Test]
    public function generate_sas_uri_stamps_the_share_resource_and_preserves_existing_query(): void
    {
        $share = new ShareClient(
            new Uri('https://account.file.core.windows.net/share?custom=value'),
            new StorageSharedKeyCredential('account', base64_encode(str_repeat('x', 32))),
        );

        $sas = $share->generateSasUri(
            ShareSasBuilder::new()
                ->setPermissions(new ShareSasPermissions(list: true))
                ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z')),
        );
        $query = $this->query($sas);

        self::assertSame('s', $query['sr'] ?? null);
        self::assertSame('l', $query['sp'] ?? null);
        self::assertSame('value', $query['custom'] ?? null);
    }

    #[Test]
    public function generate_sas_uri_clears_any_file_path_state_from_a_reused_builder(): void
    {
        $credential = new StorageSharedKeyCredential('account', base64_encode(str_repeat('x', 32)));
        $builder = ShareSasBuilder::new()
            ->setPermissions(new ShareFileSasPermissions(read: true))
            ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z'));

        (new ShareFileClient(new Uri('https://account.file.core.windows.net/share/path/file.txt'), $credential))
            ->generateSasUri($builder);

        $sas = (new ShareClient(new Uri('https://account.file.core.windows.net/share'), $credential))
            ->generateSasUri($builder->setPermissions(new ShareSasPermissions(list: true)));
        $query = $this->query($sas);

        self::assertSame('s', $query['sr'] ?? null);
        self::assertSame('l', $query['sp'] ?? null);
    }

    #[Test]
    public function generate_sas_uri_throws_when_there_is_no_shared_key_credential(): void
    {
        $this->expectException(UnableToGenerateSasException::class);

        (new ShareClient(new Uri('https://account.file.core.windows.net/share')))->generateSasUri(ShareSasBuilder::new());
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
