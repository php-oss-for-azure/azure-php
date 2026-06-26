<?php

declare(strict_types=1);

namespace AzureOss\Storage\Queue\Exceptions;

use AzureOss\Storage\Queue\Models\QueueErrorCode;

class QueueStorageException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?QueueErrorCode $errorCode = null,
        public readonly ?string $errorCodeValue = null,
        public readonly ?string $requestId = null,
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
