<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity\Integration;

use AzureOss\Identity\TokenRequestContext;
use AzureOss\Identity\WorkloadIdentityCredential;
use AzureOss\Tests\RequiresEnvironmentVariables;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WorkloadIdentityCredentialTest extends TestCase
{
    use RequiresEnvironmentVariables;

    #[Test]
    public function get_token_works_with_a_federated_token_file(): void
    {
        self::getRequiredEnvironmentVariable('AZURE_TENANT_ID');
        self::getRequiredEnvironmentVariable('AZURE_CLIENT_ID');
        self::getRequiredEnvironmentVariable('AZURE_FEDERATED_TOKEN_FILE');

        $credential = new WorkloadIdentityCredential;
        $token = $credential->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));

        self::assertGreaterThan(0, strlen($token->token));
        self::assertGreaterThan((new \DateTimeImmutable)->getTimestamp(), $token->expiresOn->getTimestamp());
        self::assertSame('Bearer', $token->tokenType);
    }
}
