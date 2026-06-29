<?php

declare(strict_types=1);

namespace AzureOss\Storage\Common\Helpers;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

/**
 * @internal
 */
final class ConnectionStringHelper
{
    private const DEV_CONNECTION_STRING_SHORTCUT = 'UseDevelopmentStorage=true';

    private const DEV_BLOB_ENDPOINT = 'http://127.0.0.1:10000/devstoreaccount1';

    private const DEV_QUEUE_ENDPOINT = 'http://127.0.0.1:10001/devstoreaccount1';

    private const DEV_BLOB_ACCOUNT_NAME = 'devstoreaccount1';

    private const DEV_BLOB_ACCOUNT_KEY = 'Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==';

    public static function getBlobEndpoint(string $connectionString): ?UriInterface
    {
        return self::getServiceEndpoint(
            $connectionString,
            endpointKey: 'BlobEndpoint',
            endpointSubdomain: 'blob',
            developmentEndpoint: self::DEV_BLOB_ENDPOINT,
        );
    }

    public static function getQueueEndpoint(string $connectionString): ?UriInterface
    {
        return self::getServiceEndpoint(
            $connectionString,
            endpointKey: 'QueueEndpoint',
            endpointSubdomain: 'queue',
            developmentEndpoint: self::DEV_QUEUE_ENDPOINT,
        );
    }

    public static function getFileEndpoint(string $connectionString): ?UriInterface
    {
        return self::getServiceEndpoint(
            $connectionString,
            endpointKey: 'FileEndpoint',
            endpointSubdomain: 'file',
        );
    }

    public static function getAccountName(string $connectionString): ?string
    {
        if ($connectionString === self::DEV_CONNECTION_STRING_SHORTCUT) {
            return self::DEV_BLOB_ACCOUNT_NAME;
        }

        return self::getSegments($connectionString)['AccountName'] ?? null;
    }

    public static function getAccountKey(string $connectionString): ?string
    {
        if ($connectionString === self::DEV_CONNECTION_STRING_SHORTCUT) {
            return self::DEV_BLOB_ACCOUNT_KEY;
        }

        return self::getSegments($connectionString)['AccountKey'] ?? null;
    }

    public static function getSas(string $connectionString): ?string
    {
        return self::getSegments($connectionString)['SharedAccessSignature'] ?? null;
    }

    private static function getServiceEndpoint(
        string $connectionString,
        string $endpointKey,
        string $endpointSubdomain,
        ?string $developmentEndpoint = null,
    ): ?UriInterface {
        if ($connectionString === self::DEV_CONNECTION_STRING_SHORTCUT && $developmentEndpoint !== null) {
            return new Uri($developmentEndpoint);
        }

        $segments = self::getSegments($connectionString);

        if (isset($segments[$endpointKey])) {
            $uri = $segments[$endpointKey];
        } elseif (isset($segments['AccountName'], $segments['EndpointSuffix'])) {
            $uri = sprintf('%s.%s.%s', $segments['AccountName'], $endpointSubdomain, $segments['EndpointSuffix']);
        } else {
            return null;
        }

        $uriWithoutScheme = preg_replace('(^https?://)', '', $uri);
        $scheme = $segments['DefaultEndpointsProtocol'] ?? 'https';

        return new Uri("$scheme://$uriWithoutScheme");
    }

    /**
     * @return array<string>
     */
    private static function getSegments(string $connectionString): array
    {
        $segments = [];
        foreach (explode(';', $connectionString) as $segment) {
            if ($segment !== '') {
                [$key, $value] = explode('=', $segment, 2);
                $segments[$key] = $value;
            }
        }

        return $segments;
    }
}
