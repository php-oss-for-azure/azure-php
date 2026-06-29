<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity\Unit;

use AzureOss\Identity\ClientCertificateCredential;
use AzureOss\Identity\ClientSecretCredential;
use AzureOss\Identity\CredentialUnavailableException;
use AzureOss\Identity\EnvironmentCredential;
use AzureOss\Identity\EnvironmentCredentialOptions;
use AzureOss\Identity\TokenCredential;
use AzureOss\Identity\TokenRequestContext;
use AzureOss\Tests\LoadsFixtures;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnvironmentCredentialTest extends TestCase
{
    use LoadsFixtures;

    /** @var array<string, string|false> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $names = [
            'AZURE_TENANT_ID',
            'AZURE_CLIENT_ID',
            'AZURE_CLIENT_SECRET',
            'AZURE_CLIENT_CERTIFICATE_PATH',
            'AZURE_CLIENT_CERTIFICATE_PASSWORD',
        ];

        foreach ($names as $name) {
            $this->originalEnv[$name] = getenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv("{$name}={$value}");
            }
        }

        parent::tearDown();
    }

    private function setEnv(string $name, ?string $value): void
    {
        if ($value === null) {
            putenv($name);

            return;
        }

        putenv("{$name}={$value}");
    }

    private function getCredential(EnvironmentCredential $credential): ?TokenCredential
    {
        $invoker = \Closure::bind(
            static fn (): ?TokenCredential => $credential->getCredential(),
            null,
            $credential,
        );

        return $invoker();
    }

    private function authorityHostFromClientSecretCredential(ClientSecretCredential $credential): string
    {
        $invoker = \Closure::bind(
            static fn (ClientSecretCredential $credential): string => $credential->options->authorityHost,
            null,
            ClientSecretCredential::class,
        );

        return $invoker($credential);
    }

    #[Test]
    public function get_token_throws_credential_unavailable_when_env_is_not_configured(): void
    {
        $this->setEnv('AZURE_TENANT_ID', null);
        $this->setEnv('AZURE_CLIENT_ID', null);
        $this->setEnv('AZURE_CLIENT_SECRET', null);
        $this->setEnv('AZURE_CLIENT_CERTIFICATE_PATH', null);
        $this->setEnv('AZURE_CLIENT_CERTIFICATE_PASSWORD', null);

        $this->expectException(CredentialUnavailableException::class);

        (new EnvironmentCredential)->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));
    }

    #[Test]
    public function selects_client_secret_credential_when_secret_is_present(): void
    {
        $this->setEnv('AZURE_TENANT_ID', 'tenant');
        $this->setEnv('AZURE_CLIENT_ID', 'client');
        $this->setEnv('AZURE_CLIENT_SECRET', 'secret');
        $this->setEnv('AZURE_CLIENT_CERTIFICATE_PATH', null);
        $this->setEnv('AZURE_CLIENT_CERTIFICATE_PASSWORD', null);

        $inner = $this->getCredential(new EnvironmentCredential);

        self::assertInstanceOf(ClientSecretCredential::class, $inner);
    }

    #[Test]
    public function selects_client_certificate_credential_when_certificate_is_present(): void
    {
        $this->setEnv('AZURE_TENANT_ID', 'tenant');
        $this->setEnv('AZURE_CLIENT_ID', 'client');
        $this->setEnv('AZURE_CLIENT_SECRET', null);
        $this->setEnv('AZURE_CLIENT_CERTIFICATE_PASSWORD', null);
        $this->setEnv(
            'AZURE_CLIENT_CERTIFICATE_PATH',
            $this->fixturePath('client-cert-pem-unencrypted.pem'),
        );

        $inner = $this->getCredential(new EnvironmentCredential);

        self::assertInstanceOf(ClientCertificateCredential::class, $inner);
    }

    #[Test]
    public function client_secret_takes_precedence_over_certificate_when_both_are_present(): void
    {
        $this->setEnv('AZURE_TENANT_ID', 'tenant');
        $this->setEnv('AZURE_CLIENT_ID', 'client');
        $this->setEnv('AZURE_CLIENT_SECRET', 'secret');
        $this->setEnv('AZURE_CLIENT_CERTIFICATE_PASSWORD', null);
        $this->setEnv(
            'AZURE_CLIENT_CERTIFICATE_PATH',
            $this->fixturePath('client-cert-pem-unencrypted.pem'),
        );

        $inner = $this->getCredential(new EnvironmentCredential);

        self::assertInstanceOf(ClientSecretCredential::class, $inner);
    }

    #[Test]
    public function passes_authority_host_option_to_selected_credential(): void
    {
        $this->setEnv('AZURE_TENANT_ID', 'tenant');
        $this->setEnv('AZURE_CLIENT_ID', 'client');
        $this->setEnv('AZURE_CLIENT_SECRET', 'secret');
        $this->setEnv('AZURE_CLIENT_CERTIFICATE_PATH', null);
        $this->setEnv('AZURE_CLIENT_CERTIFICATE_PASSWORD', null);

        $outer = new EnvironmentCredential(new EnvironmentCredentialOptions(authorityHost: 'example.invalid'));
        $inner = $this->getCredential($outer);

        self::assertInstanceOf(ClientSecretCredential::class, $inner);
        self::assertSame('example.invalid', $this->authorityHostFromClientSecretCredential($inner));
    }
}
