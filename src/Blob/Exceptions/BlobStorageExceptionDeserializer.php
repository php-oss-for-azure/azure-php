<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Exceptions;

use AzureOss\Storage\Blob\Models\BlobErrorCode;
use AzureOss\Storage\Common\Exceptions\RequestExceptionDeserializer;
use AzureOss\Storage\Common\Exceptions\StorageErrorResponse;
use GuzzleHttp\Exception\RequestException;

/**
 * @internal
 */
final class BlobStorageExceptionDeserializer implements RequestExceptionDeserializer
{
    public function deserialize(RequestException $e): \Exception
    {
        $response = $e->getResponse();
        if ($response === null) {
            return $e;
        }

        $error = StorageErrorResponse::fromResponse($response);
        if ($error === null) {
            return $e;
        }

        return new BlobStorageException(
            $error->message,
            previous: $e,
            errorCode: BlobErrorCode::tryFrom($error->code),
            errorCodeValue: $error->code,
            requestId: $error->requestId,
            statusCode: $error->statusCode,
        );
    }
}
