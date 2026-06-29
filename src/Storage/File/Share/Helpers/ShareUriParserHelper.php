<?php

declare(strict_types=1);

namespace AzureOss\Storage\File\Share\Helpers;

use AzureOss\Storage\Common\Helpers\StorageUriParserHelper;
use AzureOss\Storage\File\Share\Exceptions\InvalidShareUriException;
use Psr\Http\Message\UriInterface;

/**
 * @internal
 */
final class ShareUriParserHelper
{
    public static function getShareName(UriInterface $uri): string
    {
        $segments = self::getPathSegments($uri);

        if (StorageUriParserHelper::isDevelopmentUri($uri)) {
            array_shift($segments);
        }

        if (count($segments) === 0) {
            throw new InvalidShareUriException;
        }

        return $segments[0];
    }

    public static function getResourcePath(UriInterface $uri): string
    {
        $segments = self::getPathSegments($uri);

        if (StorageUriParserHelper::isDevelopmentUri($uri)) {
            array_shift($segments);
        }

        array_shift($segments);

        if (count($segments) === 0) {
            throw new InvalidShareUriException;
        }

        return implode('/', $segments);
    }

    /**
     * @return string[]
     */
    private static function getPathSegments(UriInterface $uri): array
    {
        return array_values(
            array_filter(
                explode('/', $uri->getPath()),
                static fn (string $value): bool => $value !== '',
            ),
        );
    }
}
