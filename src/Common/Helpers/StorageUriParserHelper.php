<?php

declare(strict_types=1);

namespace AzureOss\Storage\Common\Helpers;

use Psr\Http\Message\UriInterface;

/**
 * @internal
 */
final class StorageUriParserHelper
{
    public static function isDevelopmentUri(UriInterface $uri): bool
    {
        $segments = array_values(
            array_filter(
                explode('/', $uri->getPath()),
                fn (string $value) => $value !== '',
            ),
        );

        return ($segments[0] ?? null) === 'devstoreaccount1';
    }
}
