<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Queue\Unit;

use AzureOss\Storage\Queue\Exceptions\QueueStorageException;
use AzureOss\Storage\Queue\Exceptions\QueueStorageExceptionDeserializer;
use AzureOss\Storage\Queue\Models\QueueErrorCode;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueueStorageExceptionDeserializerTest extends TestCase
{
    #[Test]
    public function it_deserializes_known_queue_error_codes(): void
    {
        $exception = $this->deserialize(
            new Response(
                404,
                ['x-ms-request-id' => 'request-id'],
                '<Error><Code>QueueNotFound</Code><Message>The queue was not found.</Message></Error>',
            ),
        );

        self::assertSame('The queue was not found.', $exception->getMessage());
        self::assertSame(QueueErrorCode::QueueNotFound, $exception->errorCode);
        self::assertSame('QueueNotFound', $exception->errorCodeValue);
        self::assertSame('request-id', $exception->requestId);
        self::assertSame(404, $exception->statusCode);
    }

    #[Test]
    public function it_preserves_unknown_queue_error_codes(): void
    {
        $exception = $this->deserialize(
            new Response(
                409,
                ['x-ms-request-id' => 'request-id'],
                '<Error><Code>FutureQueueError</Code><Message>A future code.</Message></Error>',
            ),
        );

        self::assertNull($exception->errorCode);
        self::assertSame('FutureQueueError', $exception->errorCodeValue);
    }

    #[Test]
    public function it_deserializes_header_only_queue_error_codes(): void
    {
        $exception = $this->deserialize(
            new Response(
                404,
                [
                    'x-ms-error-code' => 'MessageNotFound',
                    'x-ms-request-id' => 'request-id',
                ],
            ),
        );

        self::assertSame(QueueErrorCode::MessageNotFound, $exception->errorCode);
        self::assertSame('MessageNotFound', $exception->errorCodeValue);
        self::assertSame('request-id', $exception->requestId);
        self::assertSame(404, $exception->statusCode);
    }

    private function deserialize(Response $response): QueueStorageException
    {
        $exception = (new QueueStorageExceptionDeserializer)->deserialize(
            new RequestException('Azure request failed.', new Request('GET', '/'), $response),
        );

        self::assertInstanceOf(QueueStorageException::class, $exception);

        return $exception;
    }
}
