<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity\Functional;

use AzureOss\Identity\AuthenticationFailedException;
use AzureOss\Identity\CredentialUnavailableException;
use AzureOss\Identity\TokenRequestContext;
use AzureOss\Identity\WorkloadIdentityCredential;
use AzureOss\Identity\WorkloadIdentityCredentialOptions;
use AzureOss\Tests\Identity\Support\FakeHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class WorkloadIdentityCredentialTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->envNames() as $name) {
            $this->originalEnv[$name] = getenv($name);
        }

        $this->setEnv('AZURE_TENANT_ID', 'tenant');
        $this->setEnv('AZURE_CLIENT_ID', 'client');
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

    /**
     * @return list<string>
     */
    private function envNames(): array
    {
        return [
            'AZURE_TENANT_ID',
            'AZURE_CLIENT_ID',
            'AZURE_FEDERATED_TOKEN_FILE',
        ];
    }

    private function setEnv(string $name, ?string $value): void
    {
        if ($value === null) {
            putenv($name);

            return;
        }

        putenv("{$name}={$value}");
    }

    /**
     * @param  array<string, string|null>  $vars
     */
    private function setEnvs(array $vars): void
    {
        foreach ($vars as $name => $value) {
            $this->setEnv($name, $value);
        }
    }

    private function createTokenFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'azure-wi-');
        self::assertIsString($path);

        file_put_contents($path, $contents);

        return $path;
    }

    private function tokenResponseBody(string $token): string
    {
        $json = json_encode([
            'access_token' => $token,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode Azure token response.');
        }

        return $json;
    }

    private function createHttpClient(ResponseInterface ...$responses): FakeHttpClient
    {
        return new FakeHttpClient(array_values($responses));
    }

    #[Test]
    public function unavailable_when_required_configuration_is_missing(): void
    {
        $this->setEnvs([
            'AZURE_TENANT_ID' => null,
            'AZURE_CLIENT_ID' => null,
            'AZURE_FEDERATED_TOKEN_FILE' => null,
        ]);

        $this->expectException(CredentialUnavailableException::class);
        $this->expectExceptionMessage('The workload options are not fully configured');

        (new WorkloadIdentityCredential)->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));
    }

    #[Test]
    public function unavailable_when_token_file_is_missing(): void
    {
        putenv('AZURE_FEDERATED_TOKEN_FILE=/path/does/not/exist');

        $this->expectException(CredentialUnavailableException::class);

        (new WorkloadIdentityCredential)->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));
    }

    #[Test]
    public function unavailable_when_token_file_is_empty(): void
    {
        $path = $this->createTokenFile('');
        putenv("AZURE_FEDERATED_TOKEN_FILE={$path}");

        try {
            $this->expectException(CredentialUnavailableException::class);

            (new WorkloadIdentityCredential)->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function exchanges_the_federated_token_file_for_an_access_token(): void
    {
        $path = $this->createTokenFile('file-assertion');
        putenv("AZURE_FEDERATED_TOKEN_FILE={$path}");

        $httpClient = $this->createHttpClient(
            new Response(200, ['Content-Type' => 'application/json'], $this->tokenResponseBody('azure-access-token')),
        );
        $factory = new HttpFactory;

        try {
            $credential = new WorkloadIdentityCredential(new WorkloadIdentityCredentialOptions(
                httpClient: $httpClient,
                requestFactory: $factory,
                streamFactory: $factory,
            ));

            $token = $credential->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));

            self::assertSame('azure-access-token', $token->token);
            self::assertCount(1, $httpClient->requests);

            parse_str((string) $httpClient->requests[0]->getBody(), $body);

            self::assertSame('file-assertion', $body['client_assertion'] ?? null);
            self::assertSame('client_credentials', $body['grant_type'] ?? null);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function options_override_environment_configuration(): void
    {
        $path = $this->createTokenFile('file-assertion');
        $httpClient = $this->createHttpClient(
            new Response(200, ['Content-Type' => 'application/json'], $this->tokenResponseBody('azure-access-token')),
        );
        $factory = new HttpFactory;

        $this->setEnvs([
            'AZURE_TENANT_ID' => 'env-tenant',
            'AZURE_CLIENT_ID' => 'env-client',
            'AZURE_FEDERATED_TOKEN_FILE' => '/env/token/file',
        ]);

        try {
            $credential = new WorkloadIdentityCredential(new WorkloadIdentityCredentialOptions(
                tenantId: 'options-tenant',
                clientId: 'options-client',
                tokenFilePath: $path,
                httpClient: $httpClient,
                requestFactory: $factory,
                streamFactory: $factory,
            ));

            $credential->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));

            self::assertCount(1, $httpClient->requests);
            self::assertSame(
                'https://login.microsoftonline.com/options-tenant/oauth2/v2.0/token',
                (string) $httpClient->requests[0]->getUri(),
            );

            parse_str((string) $httpClient->requests[0]->getBody(), $body);

            self::assertSame('options-client', $body['client_id'] ?? null);
            self::assertSame('file-assertion', $body['client_assertion'] ?? null);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function throws_authentication_failed_when_the_token_endpoint_returns_non_success(): void
    {
        $path = $this->createTokenFile('file-assertion');
        $factory = new HttpFactory;

        try {
            $credential = new WorkloadIdentityCredential(new WorkloadIdentityCredentialOptions(
                tokenFilePath: $path,
                httpClient: $this->createHttpClient(new Response(401, [], 'unauthorized')),
                requestFactory: $factory,
                streamFactory: $factory,
            ));

            $this->expectException(AuthenticationFailedException::class);
            $this->expectExceptionMessage('HTTP 401');

            $credential->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function wraps_unexpected_failures_from_the_http_pipeline(): void
    {
        $path = $this->createTokenFile('file-assertion');
        $factory = new HttpFactory;

        try {
            $credential = new WorkloadIdentityCredential(new WorkloadIdentityCredentialOptions(
                tokenFilePath: $path,
                httpClient: new FakeHttpClient([new \RuntimeException('boom')]),
                requestFactory: $factory,
                streamFactory: $factory,
            ));

            $this->expectException(AuthenticationFailedException::class);
            $this->expectExceptionMessage('Failed to authenticate with Azure using workload identity');

            $credential->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function wraps_invalid_token_responses_from_azure(): void
    {
        $path = $this->createTokenFile('file-assertion');
        $factory = new HttpFactory;

        try {
            $credential = new WorkloadIdentityCredential(new WorkloadIdentityCredentialOptions(
                tokenFilePath: $path,
                httpClient: $this->createHttpClient(new Response(200, [], '{"not":"a-token"}')),
                requestFactory: $factory,
                streamFactory: $factory,
            ));

            $this->expectException(AuthenticationFailedException::class);
            $this->expectExceptionMessage('Failed to authenticate with Azure using workload identity');

            $credential->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));
        } finally {
            @unlink($path);
        }
    }
}
