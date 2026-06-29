<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\File\Share\Unit;

use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Sas\SasIpRange;
use AzureOss\Storage\File\Share\Exceptions\UnableToGenerateSasException;
use AzureOss\Storage\File\Share\Sas\ShareFileSasPermissions;
use AzureOss\Storage\File\Share\Sas\ShareSasBuilder;
use AzureOss\Storage\File\Share\Sas\ShareSasPermissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShareSasBuilderTest extends TestCase
{
    private StorageSharedKeyCredential $credential;

    protected function setUp(): void
    {
        $this->credential = new StorageSharedKeyCredential(
            'account',
            base64_encode(str_repeat('x', 32)),
        );
    }

    #[Test]
    public function it_signs_share_and_path_resources(): void
    {
        $shareQuery = $this->buildQuery(
            ShareSasBuilder::new()
                ->setShareName('share')
                ->setPermissions(new ShareSasPermissions(list: true))
                ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z')),
        );
        $directoryQuery = $this->buildQuery(
            ShareSasBuilder::new()
                ->setShareName('share')
                ->setFilePath('nested/path')
                ->setPermissions(new ShareFileSasPermissions(read: true))
                ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z')),
        );
        $fileQuery = $this->buildQuery(
            ShareSasBuilder::new()
                ->setShareName('share')
                ->setFilePath('nested/file.txt')
                ->setPermissions(new ShareFileSasPermissions(read: true))
                ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z')),
        );

        self::assertSame('s', $shareQuery['sr'] ?? null);
        self::assertSame('f', $directoryQuery['sr'] ?? null);
        self::assertSame('f', $fileQuery['sr'] ?? null);
        self::assertSame('r', $directoryQuery['sp'] ?? null);
        self::assertArrayNotHasKey('sdd', $fileQuery);
        self::assertArrayNotHasKey('sdd', $directoryQuery);
        self::assertNotSame($shareQuery['sig'] ?? null, $directoryQuery['sig'] ?? null);
        self::assertNotSame($directoryQuery['sig'] ?? null, $fileQuery['sig'] ?? null);
    }

    #[Test]
    public function it_includes_the_signed_ip_range_when_requested(): void
    {
        $query = $this->buildQuery(
            ShareSasBuilder::new()
                ->setShareName('share')
                ->setFilePath('nested/file.txt')
                ->setPermissions(new ShareFileSasPermissions(read: true))
                ->setIPRange(new SasIpRange('0.0.0.0', '255.255.255.255'))
                ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z')),
        );

        self::assertSame('0.0.0.0-255.255.255.255', $query['sip'] ?? null);
    }

    #[Test]
    public function it_includes_response_header_overrides(): void
    {
        $query = $this->buildQuery(
            ShareSasBuilder::new()
                ->setShareName('share')
                ->setFilePath('nested/file.txt')
                ->setPermissions(new ShareFileSasPermissions(read: true))
                ->setCacheControl('max-age=60')
                ->setContentDisposition('attachment')
                ->setContentEncoding('gzip')
                ->setContentLanguage('en-US')
                ->setContentType('text/plain')
                ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z')),
        );

        self::assertSame('max-age=60', $query['rscc'] ?? null);
        self::assertSame('attachment', $query['rscd'] ?? null);
        self::assertSame('gzip', $query['rsce'] ?? null);
        self::assertSame('en-US', $query['rscl'] ?? null);
        self::assertSame('text/plain', $query['rsct'] ?? null);
    }

    #[Test]
    public function it_normalizes_backslashes_in_signed_paths(): void
    {
        $slashQuery = $this->buildQuery(
            ShareSasBuilder::new()
                ->setShareName('share')
                ->setFilePath('nested/file.txt')
                ->setPermissions(new ShareFileSasPermissions(read: true))
                ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z')),
        );
        $backslashQuery = $this->buildQuery(
            ShareSasBuilder::new()
                ->setShareName('share')
                ->setFilePath('nested\\file.txt')
                ->setPermissions(new ShareFileSasPermissions(read: true))
                ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z')),
        );

        self::assertSame($slashQuery['sig'] ?? null, $backslashQuery['sig'] ?? null);
    }

    #[Test]
    public function it_requires_permissions_or_identifier(): void
    {
        $this->expectException(UnableToGenerateSasException::class);

        ShareSasBuilder::new()
            ->setShareName('share')
            ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z'))
            ->build($this->credential);
    }

    #[Test]
    public function it_requires_a_share_name(): void
    {
        $this->expectException(UnableToGenerateSasException::class);
        $this->expectExceptionMessage('A share name is required to generate a SAS.');

        ShareSasBuilder::new()
            ->setPermissions(new ShareSasPermissions(read: true))
            ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z'))
            ->build($this->credential);
    }

    #[Test]
    public function it_requires_an_expiration_time_without_a_stored_access_policy(): void
    {
        $this->expectException(UnableToGenerateSasException::class);
        $this->expectExceptionMessage(
            'An expiration time is required to generate a SAS without a stored access policy identifier.',
        );

        ShareSasBuilder::new()
            ->setShareName('share')
            ->setPermissions(new ShareSasPermissions(read: true))
            ->build($this->credential);
    }

    /**
     * @return array<string, string>
     */
    private function buildQuery(ShareSasBuilder $builder): array
    {
        parse_str($builder->build($this->credential), $query);

        /** @var array<string, string> $query */
        return $query;
    }
}
