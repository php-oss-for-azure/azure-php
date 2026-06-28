<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Blob\Unit;

use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use AzureOss\Storage\Blob\Sas\BlobSasPermissions;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobSasBuilderTest extends TestCase
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
    public function it_signs_snapshot_and_version_resources(): void
    {
        $baseQuery = $this->buildQuery();
        $snapshotQuery = $this->buildQuery(snapshot: '2026-06-28T10:20:30.1234567Z');
        $versionQuery = $this->buildQuery(versionId: '2026-06-28T11:20:30.1234567Z');

        self::assertSame('b', $baseQuery['sr'] ?? null);
        self::assertSame('bs', $snapshotQuery['sr'] ?? null);
        self::assertSame('bv', $versionQuery['sr'] ?? null);
        self::assertNotSame($baseQuery['sig'] ?? null, $snapshotQuery['sig'] ?? null);
        self::assertNotSame($baseQuery['sig'] ?? null, $versionQuery['sig'] ?? null);
        self::assertArrayNotHasKey('sst', $snapshotQuery);
        self::assertArrayNotHasKey('sst', $versionQuery);
    }

    /**
     * @return array<string, string>
     */
    private function buildQuery(?string $snapshot = null, ?string $versionId = null): array
    {
        $builder = BlobSasBuilder::new()
            ->setContainerName('container')
            ->setBlobName('blob')
            ->setPermissions(new BlobSasPermissions(read: true))
            ->setExpiresOn(new \DateTimeImmutable('2030-01-01T00:00:00Z'))
            ->setSnapshot($snapshot)
            ->setBlobVersionId($versionId);

        parse_str($builder->build($this->credential), $query);

        /** @var array<string, string> $query */
        return $query;
    }
}
