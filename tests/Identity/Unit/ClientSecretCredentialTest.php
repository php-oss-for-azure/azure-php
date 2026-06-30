<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity\Unit;

use AzureOss\Identity\AuthenticationFailedException;
use AzureOss\Identity\ClientSecretCredential;
use AzureOss\Identity\ClientSecretCredentialOptions;
use AzureOss\Identity\TokenRequestContext;
use AzureOss\Tests\Identity\Support\FakeHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClientSecretCredentialTest extends TestCase
{
    private function tokenResponseBody(string $token): string
    {
        $json = json_encode([
            'access_token' => $token,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode token response.');
        }

        return $json;
    }

    private function createCredential(FakeHttpClient $httpClient): ClientSecretCredential
    {
        $factory = new HttpFactory;

        return new ClientSecretCredential(
            'tenant',
            'client',
            'secret',
            new ClientSecretCredentialOptions(
                authorityHost: 'example.invalid',
                httpClient: $httpClient,
                requestFactory: $factory,
                streamFactory: $factory,
            ),
        );
    }

    #[Test]
    public function sends_the_expected_oauth_request_and_returns_the_token(): void
    {
        $httpClient = new FakeHttpClient([
            new Response(200, [], $this->tokenResponseBody('access-token')),
        ]);

        $token = $this->createCredential($httpClient)->getToken(new TokenRequestContext([
            'https://graph.microsoft.com/.default',
            'https://vault.azure.net/.default',
        ]));

        self::assertSame('access-token', $token->token);
        self::assertCount(1, $httpClient->requests);

        $request = $httpClient->requests[0];

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.invalid/tenant/oauth2/v2.0/token', (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));

        parse_str((string) $request->getBody(), $body);

        self::assertSame('client_credentials', $body['grant_type'] ?? null);
        self::assertSame('client', $body['client_id'] ?? null);
        self::assertSame('secret', $body['client_secret'] ?? null);
        self::assertSame('https://graph.microsoft.com/.default https://vault.azure.net/.default', $body['scope'] ?? null);
    }

    #[Test]
    public function throws_authentication_failed_when_azure_returns_non_success(): void
    {
        $this->expectException(AuthenticationFailedException::class);
        $this->expectExceptionMessage('Failed to authenticate with Azure');

        $this->createCredential(new FakeHttpClient([
            new Response(401, [], 'unauthorized'),
        ]))->getToken(new TokenRequestContext(['scope']));
    }

    #[Test]
    public function throws_authentication_failed_when_azure_returns_an_invalid_token_response(): void
    {
        $this->expectException(AuthenticationFailedException::class);
        $this->expectExceptionMessage('Failed to authenticate with Azure');

        $this->createCredential(new FakeHttpClient([
            new Response(200, [], '{"not":"a-token"}'),
        ]))->getToken(new TokenRequestContext(['scope']));
    }
}
