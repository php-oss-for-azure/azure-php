<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

/**
 * Specifies optional datasets to include when listing blob containers.
 */
enum BlobContainerInclude: string
{
    case METADATA = 'metadata';
    case DELETED = 'deleted';
    case SYSTEM = 'system';
}
