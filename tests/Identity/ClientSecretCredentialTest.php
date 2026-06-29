<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity;

use AzureOss\Identity\AuthenticationFailedException;
use AzureOss\Identity\ClientSecretCredential;
use AzureOss\Identity\TokenRequestContext;
use AzureOss\Tests\RequiresEnvironmentVariables;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClientSecretCredentialTest extends TestCase
{
    use RequiresEnvironmentVariables;

    #[Test]
    public function get_token_works(): void
    {
        $tenantId = self::getRequiredEnvironmentVariable('AZURE_TENANT_ID');
        $clientId = self::getRequiredEnvironmentVariable('AZURE_CLIENT_ID');
        $clientSecret = self::getRequiredEnvironmentVariable('AZURE_CLIENT_SECRET');

        $credential = new ClientSecretCredential($tenantId, $clientId, $clientSecret);
        $token = $credential->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));

        self::assertGreaterThan(0, strlen($token->token));
        self::assertGreaterThan((new \DateTimeImmutable)->getTimestamp(), $token->expiresOn->getTimestamp());
        self::assertEquals('Bearer', $token->tokenType);
    }

    #[Test]
    public function get_token_throws_authentication_failed_exception_when_credentials_are_invalid(): void
    {
        $this->expectException(AuthenticationFailedException::class);

        (new ClientSecretCredential('invalid', 'invalid', 'invalid'))
            ->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));
    }
}
