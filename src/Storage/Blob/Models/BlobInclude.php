<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

/**
 * Specifies optional datasets to include when listing blobs.
 */
enum BlobInclude: string
{
    case SNAPSHOTS = 'snapshots';
    case METADATA = 'metadata';
    case UNCOMMITTED_BLOBS = 'uncommittedblobs';
    case COPY = 'copy';
    case DELETED = 'deleted';
    case TAGS = 'tags';
    case VERSIONS = 'versions';
    case DELETED_WITH_VERSIONS = 'deletedwithversions';
}
