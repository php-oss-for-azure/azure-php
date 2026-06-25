<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

final class BreakBlobLeaseOptions
{
    public function __construct(
        public ?int $breakPeriodSeconds = null,
        public ?BlobRequestConditions $conditions = null,
    ) {}
}
