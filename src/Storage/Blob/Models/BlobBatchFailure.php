<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

use AzureOss\Storage\Blob\BlobClient;
use AzureOss\Storage\Blob\Exceptions\BlobStorageException;

/**
 * Describes an operation of a blob batch that the service did not complete.
 */
final class BlobBatchFailure
{
    /**
     * @param  int  $index  Zero-based position of the operation in the batch, in the order operations were added or the blobs passed to `deleteBlobs()` were iterated; not the caller's array key.
     * @param  BlobClient|string  $blob  The blob the operation targeted, exactly as it was passed in.
     * @param  BlobStorageException  $exception  The error the service returned for the operation, with its error code, message, request ID and status code.
     */
    public function __construct(
        public readonly int $index,
        public readonly BlobClient|string $blob,
        public readonly BlobStorageException $exception,
    ) {}
}
