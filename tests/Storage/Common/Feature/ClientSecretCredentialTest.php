<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Common\Feature;

use AzureOss\Identity\ClientSecretCredential;
use AzureOss\Identity\TokenRequestContext;
use AzureOss\Storage\Common\Middleware\ClientFactory;
use AzureOss\Tests\RequiresEnvironmentVariables;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClientSecretCredentialTest extends TestCase
{
    use RequiresEnvironmentVariables;

    #[Test]
    public function get_token_works(): void
    {
        $tenantId = self::getRequiredEnvironmentVariable('AZURE_STORAGE_BLOB_TENANT_ID');
        $clientId = self::getRequiredEnvironmentVariable('AZURE_STORAGE_BLOB_CLIENT_ID');
        $clientSecret = self::getRequiredEnvironmentVariable('AZURE_STORAGE_BLOB_CLIENT_SECRET');

        $credential = new ClientSecretCredential($tenantId, $clientId, $clientSecret);

        $token = $credential->getToken(new TokenRequestContext(['https://storage.azure.com/.default']));

        self::assertGreaterThan(0, strlen($token->token));
        self::assertGreaterThan((new \DateTimeImmutable)->getTimestamp(), $token->expiresOn->getTimestamp());
    }

    #[Test]
    public function making_request_works(): void
    {
        $endpoint = self::getRequiredEnvironmentVariable('AZURE_STORAGE_BLOB_ENDPOINT');
        $tenantId = self::getRequiredEnvironmentVariable('AZURE_STORAGE_BLOB_TENANT_ID');
        $clientId = self::getRequiredEnvironmentVariable('AZURE_STORAGE_BLOB_CLIENT_ID');
        $clientSecret = self::getRequiredEnvironmentVariable('AZURE_STORAGE_BLOB_CLIENT_SECRET');

        $client = (new ClientFactory)->create(
            credential: new ClientSecretCredential($tenantId, $clientId, $clientSecret),
        );

        $response = $client->get($endpoint.'/', [
            RequestOptions::QUERY => [
                'comp' => 'list',
            ],
        ]);

        self::assertEquals(200, $response->getStatusCode());
    }
}
