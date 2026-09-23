<?php

declare(strict_types=1);

namespace AzureOss\Storage\Common\Middleware;

use Psr\Http\Message\RequestInterface;

/**
 * Authorizes storage requests, both as Guzzle middleware and for requests sent outside the HTTP client.
 *
 * @internal
 */
interface RequestSigner
{
    /** Returns Guzzle middleware that signs every request passing through it. */
    public function __invoke(callable $handler): \Closure;

    /**
     * Returns the request with its `Authorization` header set. Call it after every header, including
     * `x-ms-date`, is set, because a shared key signature covers the headers.
     */
    public function sign(RequestInterface $request): RequestInterface;
}
