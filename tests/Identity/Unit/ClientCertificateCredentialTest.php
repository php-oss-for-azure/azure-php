<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity\Unit;

use AzureOss\Identity\AuthenticationFailedException;
use AzureOss\Identity\ClientCertificateCredential;
use AzureOss\Identity\ClientCertificateCredentialOptions;
use AzureOss\Identity\TokenRequestContext;
use AzureOss\Tests\Identity\Support\FakeHttpClient;
use AzureOss\Tests\LoadsFixtures;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClientCertificateCredentialTest extends TestCase
{
    use LoadsFixtures;

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

    private function createCredential(FakeHttpClient $httpClient, string $path, ?string $password = null, bool $sendCertificateChain = false): ClientCertificateCredential
    {
        $factory = new HttpFactory;

        return new ClientCertificateCredential(
            'tenant',
            'client',
            $path,
            $password,
            new ClientCertificateCredentialOptions(
                authorityHost: 'example.invalid',
                sendCertificateChain: $sendCertificateChain,
                httpClient: $httpClient,
                requestFactory: $factory,
                streamFactory: $factory,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtPart(string $jwt, int $index): array
    {
        $parts = explode('.', $jwt);
        self::assertArrayHasKey($index, $parts);

        $decoded = base64_decode(strtr($parts[$index], '-_', '+/'), true);
        self::assertIsString($decoded);

        $json = json_decode($decoded, true);
        self::assertIsArray($json);

        $result = [];
        foreach ($json as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<string, mixed>
     */
    private function formBody(array $body): array
    {
        $result = [];
        foreach ($body as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    #[Test]
    public function sends_the_expected_oauth_request_with_a_client_assertion(): void
    {
        $httpClient = new FakeHttpClient([
            new Response(200, [], $this->tokenResponseBody('access-token')),
        ]);

        $token = $this->createCredential(
            $httpClient,
            $this->fixturePath('client-cert-pem-unencrypted.pem'),
        )->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));

        self::assertSame('access-token', $token->token);
        self::assertCount(1, $httpClient->requests);

        $request = $httpClient->requests[0];

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.invalid/tenant/oauth2/v2.0/token', (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));

        parse_str((string) $request->getBody(), $body);
        $body = $this->formBody($body);

        self::assertSame('client_credentials', $body['grant_type'] ?? null);
        self::assertSame('client', $body['client_id'] ?? null);
        self::assertSame('urn:ietf:params:oauth:client-assertion-type:jwt-bearer', $body['client_assertion_type'] ?? null);
        self::assertSame('https://graph.microsoft.com/.default', $body['scope'] ?? null);

        self::assertIsString($body['client_assertion'] ?? null);
        $header = $this->decodeJwtPart($body['client_assertion'], 0);
        $payload = $this->decodeJwtPart($body['client_assertion'], 1);

        self::assertSame('RS256', $header['alg'] ?? null);
        self::assertSame('JWT', $header['typ'] ?? null);
        self::assertArrayHasKey('x5t#S256', $header);
        self::assertSame('https://example.invalid/tenant/oauth2/v2.0/token', $payload['aud'] ?? null);
        self::assertSame('client', $payload['iss'] ?? null);
        self::assertSame('client', $payload['sub'] ?? null);
    }

    #[Test]
    public function can_include_the_certificate_chain_in_the_client_assertion_header(): void
    {
        $httpClient = new FakeHttpClient([
            new Response(200, [], $this->tokenResponseBody('access-token')),
        ]);

        $this->createCredential(
            $httpClient,
            $this->fixturePath('client-cert-pem-chain.pem'),
            sendCertificateChain: true,
        )->getToken(new TokenRequestContext(['https://graph.microsoft.com/.default']));

        parse_str((string) $httpClient->requests[0]->getBody(), $body);
        $body = $this->formBody($body);
        self::assertIsString($body['client_assertion'] ?? null);
        $header = $this->decodeJwtPart($body['client_assertion'], 0);

        self::assertIsArray($header['x5c'] ?? null);
        self::assertGreaterThanOrEqual(2, count($header['x5c']));
    }

    #[Test]
    public function throws_authentication_failed_when_azure_returns_non_success(): void
    {
        $this->expectException(AuthenticationFailedException::class);
        $this->expectExceptionMessage('Failed to authenticate with Azure');

        $this->createCredential(
            new FakeHttpClient([new Response(401, [], 'unauthorized')]),
            $this->fixturePath('client-cert-pem-unencrypted.pem'),
        )->getToken(new TokenRequestContext(['scope']));
    }

    #[Test]
    public function throws_authentication_failed_when_the_certificate_file_is_missing(): void
    {
        $this->expectException(AuthenticationFailedException::class);
        $this->expectExceptionMessage('Failed to authenticate with Azure');

        $this->createCredential(
            new FakeHttpClient([new Response(200, [], $this->tokenResponseBody('unused'))]),
            '/path/does/not/exist.pem',
        )->getToken(new TokenRequestContext(['scope']));
    }
}
