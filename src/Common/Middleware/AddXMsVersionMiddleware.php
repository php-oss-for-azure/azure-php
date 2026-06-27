<?php

declare(strict_types=1);

namespace AzureOss\Storage\Common\Middleware;

use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Helpers\StorageUriParserHelper;
use Psr\Http\Message\RequestInterface;

/**
 * @internal
 */
final class AddXMsVersionMiddleware
{
    public function __construct(
        private ?ApiVersion $version = null,
    ) {}

    public function __invoke(callable $handler): \Closure
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $version = $this->version
                ?? (StorageUriParserHelper::isDevelopmentUri($request->getUri())
                    ? ApiVersion::latestAzurite()
                    : ApiVersion::latestGA());
            $request = $request->withHeader('x-ms-version', $version->value);

            return $handler($request, $options);
        };
    }
}
