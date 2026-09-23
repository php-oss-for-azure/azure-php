<?php

declare(strict_types=1);

namespace AzureOss\Storage\Common\Middleware;

use AzureOss\Identity\TokenCredential;
use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Exceptions\RequestExceptionDeserializer;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;
use Psr\Http\Message\UriInterface;

/**
 * @internal
 */
final class ClientFactory
{
    /**
     * Creates an HTTP client with the storage middleware stack.
     *
     * @param  RequestSigner|null  $signer  Signer to authorize requests with instead of one created for `$credential`, so requests signed outside the client share its token cache.
     */
    public function create(?UriInterface $uri = null, StorageSharedKeyCredential|TokenCredential|null $credential = null, ?RequestExceptionDeserializer $exceptionDeserializer = null, HttpClientOptions $options = new HttpClientOptions, ?ApiVersion $apiVersion = null, ?RequestSigner $signer = null): Client
    {
        $handlerStack = HandlerStack::create();

        if ($exceptionDeserializer !== null) {
            $handlerStack->before('http_errors', new DeserializeExceptionMiddleware($exceptionDeserializer));
        }

        $handlerStack->push(new AddXMsClientRequestIdMiddleware);
        $handlerStack->push(new AddXMsDateHeaderMiddleware);
        $handlerStack->push(new AddXMsVersionMiddleware($apiVersion));

        if ($uri !== null) {
            $handlerStack->push(new AddDefaultQueryParamsMiddleware($uri->getQuery()));
        }

        $signer ??= $this->createRequestSigner($credential);

        if ($signer !== null) {
            $handlerStack->push($signer);
        }

        $handlerStack->push($this->createRetryMiddleware());

        return new Client(array_merge(['handler' => $handlerStack], $options->toGuzzleHttpClientConfig()));
    }

    /** Creates the signer for a credential, or null when requests are anonymous or authorized by SAS. */
    public function createRequestSigner(StorageSharedKeyCredential|TokenCredential|null $credential): ?RequestSigner
    {
        return match (true) {
            $credential instanceof StorageSharedKeyCredential => new AddSharedKeyAuthorizationHeaderMiddleware($credential),
            $credential instanceof TokenCredential => new AddEntraIdAuthorizationHeaderMiddleware($credential),
            default => null,
        };
    }

    private function createRetryMiddleware(): \Closure
    {
        return GuzzleRetryMiddleware::factory([
            'retry_on_status' => [
                408, // Request Timeout
                429, // Too Many Requests
                500, // Internal Server Error
                502, // Bad Gateway
                503, // Service Unavailable
                504, // Gateway Timeout
            ],
            'retry_on_timeout' => true,
        ]);
    }
}
