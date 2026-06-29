<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity;

use AzureOss\Identity\AuthenticationFailedException;
use AzureOss\Identity\ClientCertificateCredential;
use AzureOss\Identity\ClientCertificateCredentialOptions;
use AzureOss\Identity\TokenRequestContext;
use AzureOss\Tests\LoadsFixtures;
use AzureOss\Tests\RequiresEnvironmentVariables;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClientCertificateCredentialTest extends TestCase
{
    use LoadsFixtures, RequiresEnvironmentVariables;

    private const GRAPH_SCOPE = 'https://graph.microsoft.com/.default';

    #[Test]
    public function get_token_works_with_fixture_pem_unencrypted(): void
    {
        [$tenantId, $clientId] = $this->azureApplicationIdentity();

        $credential = new ClientCertificateCredential(
            $tenantId,
            $clientId,
            $this->fixturePath('client-cert-pem-unencrypted.pem'),
        );

        $token = $credential->getToken(new TokenRequestContext([self::GRAPH_SCOPE]));

        self::assertGreaterThan(0, strlen($token->token));
        self::assertGreaterThan((new \DateTimeImmutable)->getTimestamp(), $token->expiresOn->getTimestamp());
        self::assertSame('Bearer', $token->tokenType);
    }

    #[Test]
    public function get_token_works_with_fixture_pem_encrypted(): void
    {
        [$tenantId, $clientId] = $this->azureApplicationIdentity();

        $credential = new ClientCertificateCredential(
            $tenantId,
            $clientId,
            $this->fixturePath('client-cert-pem-encrypted.pem'),
            'fixture-pem-pass',
        );

        $token = $credential->getToken(new TokenRequestContext([self::GRAPH_SCOPE]));

        self::assertGreaterThan(0, strlen($token->token));
        self::assertGreaterThan((new \DateTimeImmutable)->getTimestamp(), $token->expiresOn->getTimestamp());
        self::assertSame('Bearer', $token->tokenType);
    }

    #[Test]
    public function get_token_works_with_fixture_pfx_without_password(): void
    {
        [$tenantId, $clientId] = $this->azureApplicationIdentity();

        $credential = new ClientCertificateCredential(
            $tenantId,
            $clientId,
            $this->fixturePath('client-cert-pfx-no-password.pfx'),
        );

        $token = $credential->getToken(new TokenRequestContext([self::GRAPH_SCOPE]));

        self::assertGreaterThan(0, strlen($token->token));
        self::assertGreaterThan((new \DateTimeImmutable)->getTimestamp(), $token->expiresOn->getTimestamp());
        self::assertSame('Bearer', $token->tokenType);
    }

    #[Test]
    public function get_token_works_with_fixture_pfx_with_password(): void
    {
        [$tenantId, $clientId] = $this->azureApplicationIdentity();

        $credential = new ClientCertificateCredential(
            $tenantId,
            $clientId,
            $this->fixturePath('client-cert-pfx-password.pfx'),
            'fixture-pfx-pass',
        );

        $token = $credential->getToken(new TokenRequestContext([self::GRAPH_SCOPE]));

        self::assertGreaterThan(0, strlen($token->token));
        self::assertGreaterThan((new \DateTimeImmutable)->getTimestamp(), $token->expiresOn->getTimestamp());
        self::assertSame('Bearer', $token->tokenType);
    }

    #[Test]
    public function get_token_works_with_fixture_pem_chain_and_send_certificate_chain(): void
    {
        [$tenantId, $clientId] = $this->azureApplicationIdentity();

        $credential = new ClientCertificateCredential(
            $tenantId,
            $clientId,
            $this->fixturePath('client-cert-pem-chain.pem'),
            null,
            new ClientCertificateCredentialOptions(sendCertificateChain: true),
        );

        $token = $credential->getToken(new TokenRequestContext([self::GRAPH_SCOPE]));

        self::assertGreaterThan(0, strlen($token->token));
        self::assertGreaterThan((new \DateTimeImmutable)->getTimestamp(), $token->expiresOn->getTimestamp());
        self::assertSame('Bearer', $token->tokenType);
    }

    #[Test]
    public function get_token_throws_authentication_failed_exception_when_credentials_are_invalid(): void
    {
        $this->expectException(AuthenticationFailedException::class);

        (new ClientCertificateCredential(
            'invalid-tenant',
            'invalid-client',
            $this->fixturePath('client-cert-pem-unencrypted.pem'),
        ))->getToken(new TokenRequestContext([self::GRAPH_SCOPE]));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function azureApplicationIdentity(): array
    {
        $tenantId = self::getRequiredEnvironmentVariable('AZURE_TENANT_ID');
        $clientId = self::getRequiredEnvironmentVariable('AZURE_CLIENT_ID');

        return [$tenantId, $clientId];
    }
}
