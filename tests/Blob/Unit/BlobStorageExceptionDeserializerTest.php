<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Unit;

use AzureOss\Storage\Blob\Exceptions\BlobStorageException;
use AzureOss\Storage\Blob\Exceptions\BlobStorageExceptionDeserializer;
use AzureOss\Storage\Blob\Models\BlobErrorCode;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobStorageExceptionDeserializerTest extends TestCase
{
    #[Test]
    public function it_deserializes_known_blob_error_codes(): void
    {
        $exception = $this->deserialize(
            new Response(
                404,
                ['x-ms-request-id' => 'request-id'],
                '<Error><Code>BlobNotFound</Code><Message>The blob was not found.</Message></Error>',
            ),
        );

        self::assertSame('The blob was not found.', $exception->getMessage());
        self::assertSame(BlobErrorCode::BlobNotFound, $exception->errorCode);
        self::assertSame('BlobNotFound', $exception->errorCodeValue);
        self::assertSame('request-id', $exception->requestId);
        self::assertSame(404, $exception->statusCode);
    }

    #[Test]
    public function it_preserves_unknown_blob_error_codes(): void
    {
        $exception = $this->deserialize(
            new Response(
                409,
                ['x-ms-request-id' => 'request-id'],
                '<Error><Code>FutureBlobError</Code><Message>A future code.</Message></Error>',
            ),
        );

        self::assertNull($exception->errorCode);
        self::assertSame('FutureBlobError', $exception->errorCodeValue);
    }

    #[Test]
    public function it_deserializes_header_only_blob_error_codes(): void
    {
        $exception = $this->deserialize(
            new Response(
                404,
                [
                    'x-ms-error-code' => 'ContainerNotFound',
                    'x-ms-request-id' => 'request-id',
                ],
            ),
        );

        self::assertSame(BlobErrorCode::ContainerNotFound, $exception->errorCode);
        self::assertSame('ContainerNotFound', $exception->errorCodeValue);
        self::assertSame('request-id', $exception->requestId);
        self::assertSame(404, $exception->statusCode);
    }

    private function deserialize(Response $response): BlobStorageException
    {
        $exception = (new BlobStorageExceptionDeserializer)->deserialize(
            new RequestException('Azure request failed.', new Request('GET', '/'), $response),
        );

        self::assertInstanceOf(BlobStorageException::class, $exception);

        return $exception;
    }
}
