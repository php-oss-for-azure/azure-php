<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage;

use AzureOss\Storage\Common\Helpers\ConnectionStringHelper;
use AzureOss\Tests\RequiresEnvironmentVariables;
use PHPUnit\Framework\TestCase;

/** @mixin TestCase */
trait ResolvesBlobConnectionSettings
{
    use RequiresEnvironmentVariables;

    protected static function getRequiredBlobEndpointEnvironmentValue(): string
    {
        $endpoint = getenv('AZURE_STORAGE_BLOB_ENDPOINT');
        if (is_string($endpoint) && $endpoint !== '') {
            return $endpoint;
        }

        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        if (is_string($connectionString) && $connectionString !== '') {
            $derived = ConnectionStringHelper::getBlobEndpoint($connectionString);

            if ($derived !== null) {
                return (string) $derived;
            }
        }

        self::markTestSkipped(
            'Missing environment variables: one of AZURE_STORAGE_BLOB_ENDPOINT, AZURE_STORAGE_CONNECTION_STRING',
        );
    }

    protected static function getRequiredBlobAccountNameEnvironmentValue(): string
    {
        $accountName = getenv('AZURE_STORAGE_BLOB_ACCOUNT_NAME');
        if (is_string($accountName) && $accountName !== '') {
            return $accountName;
        }

        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        if (is_string($connectionString) && $connectionString !== '') {
            $derived = ConnectionStringHelper::getAccountName($connectionString);

            if ($derived !== null && $derived !== '') {
                return $derived;
            }
        }

        self::markTestSkipped(
            'Missing environment variables: one of AZURE_STORAGE_BLOB_ACCOUNT_NAME, AZURE_STORAGE_CONNECTION_STRING',
        );
    }
}
