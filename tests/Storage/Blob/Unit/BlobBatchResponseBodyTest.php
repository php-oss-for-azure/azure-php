<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Blob\Unit;

use AzureOss\Storage\Blob\Exceptions\DeserializationException;
use AzureOss\Storage\Blob\Responses\BlobBatchResponseBody;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobBatchResponseBodyTest extends TestCase
{
    private const INVALID_INPUT = "HTTP/1.1 400 One of the request inputs is not valid.\r\n"
        ."x-ms-error-code: InvalidInput\r\n"
        ."x-ms-request-id: request-1\r\n"
        ."Content-Type: application/xml\r\n"
        ."\r\n"
        .'<?xml version="1.0" encoding="utf-8"?><Error><Code>InvalidInput</Code><Message>One of the request inputs is not valid.</Message></Error>';

    #[Test]
    public function it_parses_sub_responses_by_content_id(): void
    {
        $body = BlobBatchResponseBody::fromResponse(new Response(
            202,
            ['Content-Type' => 'multipart/mixed; boundary="batchresponse_66925647-d0cb-4109-b6d3-28efe3e1e5ed"'],
            "--batchresponse_66925647-d0cb-4109-b6d3-28efe3e1e5ed\r\n"
            ."Content-Type: application/http\r\n"
            ."Content-ID: 1\r\n"
            ."\r\n"
            ."HTTP/1.1 404 The specified blob does not exist.\r\n"
            ."x-ms-error-code: BlobNotFound\r\n"
            ."x-ms-request-id: 778fdc83-801e-0000-62ff-0334671e2852\r\n"
            ."Content-Type: application/xml\r\n"
            ."\r\n"
            ."<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n"
            ."<Error><Code>BlobNotFound</Code><Message>The specified blob does not exist.</Message></Error>\r\n"
            ."--batchresponse_66925647-d0cb-4109-b6d3-28efe3e1e5ed\r\n"
            ."Content-Type: application/http\r\n"
            ."Content-ID: 0\r\n"
            ."\r\n"
            ."HTTP/1.1 202 Accepted\r\n"
            ."x-ms-delete-type-permanent: true\r\n"
            ."x-ms-request-id: 778fdc83-801e-0000-62ff-03346712284f\r\n"
            ."\r\n"
            .'--batchresponse_66925647-d0cb-4109-b6d3-28efe3e1e5ed--',
        ), 2);

        self::assertNull($body->rejection);
        self::assertSame([1, 0], array_keys($body->responses));
        self::assertSame(202, $body->responses[0]->getStatusCode());
        self::assertSame('778fdc83-801e-0000-62ff-03346712284f', $body->responses[0]->getHeaderLine('x-ms-request-id'));
        self::assertSame('', (string) $body->responses[0]->getBody());
        self::assertSame(404, $body->responses[1]->getStatusCode());
        self::assertSame('BlobNotFound', $body->responses[1]->getHeaderLine('x-ms-error-code'));
        self::assertSame(
            "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n<Error><Code>BlobNotFound</Code><Message>The specified blob does not exist.</Message></Error>",
            (string) $body->responses[1]->getBody(),
        );
    }

    #[Test]
    public function it_rejects_non_multipart_responses(): void
    {
        $this->expectException(DeserializationException::class);

        BlobBatchResponseBody::fromResponse(new Response(202, ['Content-Type' => 'application/xml'], '<Error/>'), 1);
    }

    #[Test]
    public function it_rejects_successful_parts_without_content_id(): void
    {
        $this->expectException(DeserializationException::class);

        BlobBatchResponseBody::fromResponse(self::singlePartResponse(null, 'HTTP/1.1 202 Accepted'), 1);
    }

    #[Test]
    public function it_reads_single_error_part_without_content_id_as_rejection(): void
    {
        $body = BlobBatchResponseBody::fromResponse(self::singlePartResponse(null, self::INVALID_INPUT), 1);

        self::assertSame([], $body->responses);
        self::assertSame(400, $body->rejection?->getStatusCode());
        self::assertSame('InvalidInput', $body->rejection->getHeaderLine('x-ms-error-code'));
        self::assertSame('request-1', $body->rejection->getHeaderLine('x-ms-request-id'));
    }

    #[Test]
    public function it_reads_single_error_part_for_several_operations_as_rejection(): void
    {
        $body = BlobBatchResponseBody::fromResponse(self::singlePartResponse(0, self::INVALID_INPUT), 3);

        self::assertSame([], $body->responses);
        self::assertSame(400, $body->rejection?->getStatusCode());
    }

    #[Test]
    public function it_reads_single_error_part_for_one_operation_as_sub_response(): void
    {
        $body = BlobBatchResponseBody::fromResponse(self::singlePartResponse(0, self::INVALID_INPUT), 1);

        self::assertNull($body->rejection);
        self::assertSame(400, $body->responses[0]->getStatusCode());
    }

    private static function singlePartResponse(?int $contentId, string $httpResponse): Response
    {
        return new Response(
            202,
            ['Content-Type' => 'multipart/mixed; boundary=batchresponse_1'],
            "--batchresponse_1\r\nContent-Type: application/http\r\n"
            .($contentId === null ? '' : "Content-ID: {$contentId}\r\n")
            ."\r\n{$httpResponse}\r\n\r\n--batchresponse_1--",
        );
    }
}
