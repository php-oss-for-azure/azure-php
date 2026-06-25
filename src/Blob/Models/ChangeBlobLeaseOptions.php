<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

final class ChangeBlobLeaseOptions
{
    public function __construct(
        public ?BlobRequestConditions $conditions = null,
    ) {}
}
