<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Exceptions;

use AzureOss\Storage\Blob\Models\BlobBatchFailure;

/**
 * Indicates that the service accepted a blob batch but one or more of its operations failed.
 *
 * The other operations of the batch were still applied. The request ID and status code are those
 * of the batch request; each failed operation carries its own error in `$failures`.
 */
final class BlobBatchException extends BlobStorageException
{
    /**
     * @param  non-empty-list<BlobBatchFailure>  $failures  The failed operations, in batch order.
     * @param  \Throwable|null  $previous  The error of the first failed operation.
     * @param  string|null  $requestId  Request ID of the batch request.
     * @param  int|null  $statusCode  Status code of the batch response, normally 202.
     */
    public function __construct(
        string $message,
        public readonly array $failures,
        ?\Throwable $previous = null,
        ?string $requestId = null,
        ?int $statusCode = null,
    ) {
        parent::__construct($message, previous: $previous, requestId: $requestId, statusCode: $statusCode);
    }
}
