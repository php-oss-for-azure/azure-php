<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

/**
 * Configures delete blob options.
 */
final class DeleteBlobOptions
{
    public function __construct(
        public ?BlobRequestConditions $conditions = null,
        /** Controls whether snapshots are included instead of rejecting deletion when snapshots exist. */
        public ?DeleteSnapshotsOption $snapshotsOption = null,
    ) {}
}
