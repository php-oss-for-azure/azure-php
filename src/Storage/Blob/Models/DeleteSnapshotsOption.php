<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

/**
 * Specifies how snapshots are handled when deleting a base blob.
 */
enum DeleteSnapshotsOption: string
{
    /** Deletes the base blob and all of its snapshots. */
    case INCLUDE_SNAPSHOTS = 'include';

    /** Deletes all snapshots while preserving the base blob. */
    case ONLY_SNAPSHOTS = 'only';
}
