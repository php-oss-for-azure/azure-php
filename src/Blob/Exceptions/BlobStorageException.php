<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Exceptions;

use AzureOss\Storage\Blob\Models\BlobErrorCode;

class BlobStorageException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?BlobErrorCode $errorCode = null,
        public readonly ?string $errorCodeValue = null,
        public readonly ?string $requestId = null,
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
