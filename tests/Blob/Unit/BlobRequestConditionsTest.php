<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Unit;

use AzureOss\Storage\Blob\Models\BlobRequestConditions;
use AzureOss\Storage\Common\Models\ETag;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobRequestConditionsTest extends TestCase
{
    #[Test]
    public function converts_conditions_to_http_headers(): void
    {
        $conditions = new BlobRequestConditions(
            ifMatch: new ETag('"match"'),
            ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC'),
            ifNoneMatch: ETag::all(),
            ifUnmodifiedSince: new \DateTimeImmutable('2025-01-02 12:34:56 UTC'),
            leaseId: '11111111-1111-4111-8111-111111111111',
        );

        self::assertSame([
            'If-Match' => '"match"',
            'If-Modified-Since' => 'Wed, 01 Jan 2025 12:34:56 GMT',
            'If-None-Match' => '*',
            'If-Unmodified-Since' => 'Thu, 02 Jan 2025 12:34:56 GMT',
            'x-ms-lease-id' => '11111111-1111-4111-8111-111111111111',
        ], $conditions->toHeaders());
    }

    #[Test]
    public function converts_conditions_to_source_http_headers(): void
    {
        $conditions = new BlobRequestConditions(
            ifMatch: new ETag('"match"'),
            ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC'),
            ifNoneMatch: ETag::all(),
            ifUnmodifiedSince: new \DateTimeImmutable('2025-01-02 12:34:56 UTC'),
            leaseId: '11111111-1111-4111-8111-111111111111',
        );

        self::assertSame([
            'x-ms-source-if-match' => '"match"',
            'x-ms-source-if-modified-since' => 'Wed, 01 Jan 2025 12:34:56 GMT',
            'x-ms-source-if-none-match' => '*',
            'x-ms-source-if-unmodified-since' => 'Thu, 02 Jan 2025 12:34:56 GMT',
            'x-ms-source-lease-id' => '11111111-1111-4111-8111-111111111111',
        ], $conditions->toHeaders(prefix: 'x-ms-source-'));
    }

    #[Test]
    public function converts_conditions_to_lease_id_headers(): void
    {
        $conditions = new BlobRequestConditions(
            ifMatch: new ETag('"match"'),
            ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC'),
            ifNoneMatch: ETag::all(),
            ifUnmodifiedSince: new \DateTimeImmutable('2025-01-02 12:34:56 UTC'),
            leaseId: '11111111-1111-4111-8111-111111111111',
        );

        self::assertSame([
            'x-ms-lease-id' => '11111111-1111-4111-8111-111111111111',
        ], $conditions->toHeaders(
            ifMatch: false,
            ifModifiedSince: false,
            ifNoneMatch: false,
            ifUnmodifiedSince: false,
        ));
    }

    #[Test]
    public function converts_conditions_to_supported_header_subset(): void
    {
        $conditions = new BlobRequestConditions(
            ifMatch: new ETag('"match"'),
            ifModifiedSince: new \DateTimeImmutable('2025-01-01 12:34:56 UTC'),
            ifNoneMatch: ETag::all(),
            ifUnmodifiedSince: new \DateTimeImmutable('2025-01-02 12:34:56 UTC'),
            leaseId: '11111111-1111-4111-8111-111111111111',
        );

        self::assertSame([
            'If-Modified-Since' => 'Wed, 01 Jan 2025 12:34:56 GMT',
            'If-Unmodified-Since' => 'Thu, 02 Jan 2025 12:34:56 GMT',
        ], $conditions->toHeaders(ifMatch: false, ifNoneMatch: false, leaseId: false));
    }
}
