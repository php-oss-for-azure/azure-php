<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Requests;

use Psr\Http\Message\RequestInterface;

/**
 * @internal
 */
final class BlobBatchRequestBody
{
    public readonly string $boundary;

    /**
     * @param  list<RequestInterface>  $requests
     */
    public function __construct(
        public readonly array $requests,
    ) {
        $this->boundary = 'batch_'.bin2hex(random_bytes(16));
    }

    public function contentType(): string
    {
        return 'multipart/mixed; boundary='.$this->boundary;
    }

    public function toString(): string
    {
        $body = '';

        foreach ($this->requests as $contentId => $request) {
            $body .= "--{$this->boundary}\r\n"
                ."Content-Type: application/http\r\n"
                ."Content-Transfer-Encoding: binary\r\n"
                ."Content-ID: {$contentId}\r\n"
                ."\r\n"
                .self::serializeRequest($request)
                ."\r\n";
        }

        return $body."--{$this->boundary}--\r\n";
    }

    private static function serializeRequest(RequestInterface $request): string
    {
        $message = "{$request->getMethod()} {$request->getRequestTarget()} HTTP/1.1\r\n";

        foreach ($request->getHeaders() as $name => $values) {
            if (strtolower((string) $name) !== 'host') {
                $message .= $name.': '.implode(', ', $values)."\r\n";
            }
        }

        return $message."\r\n".$request->getBody();
    }
}
