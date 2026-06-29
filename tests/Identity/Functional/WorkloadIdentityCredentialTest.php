<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity\Functional;

use AzureOss\Identity\CredentialUnavailableException;
use AzureOss\Identity\TokenRequestContext;
use AzureOss\Identity\WorkloadIdentityCredential;
use AzureOss\Identity\WorkloadIdentityCredentialOptions;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class TestHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param  list<ResponseInterface>  $responses
     */
    public function __construct(private array $responses) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->responses === []) {
            throw new \RuntimeException('Unexpected HTTP request.');
        }

        $response = $this->responses[0];
        array_shift($this->responses);

        return $response;
    }
}

class WorkloadIdentityCredentialTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $names = [
            'AZURE_TENANT_ID',
            'AZURE_CLIENT_ID',
            'AZURE_FEDERATED_TOKEN_FILE',
        ];

        foreach ($names as $name) {
            $this->originalEnv[$name] = getenv($name);
        }

        putenv('AZURE_TENANT_ID=tenant');
        putenv('AZURE_CLIENT_ID=client');
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

    private function createHttpClient(ResponseInterface ...$responses): TestHttpClient
    {
        return new TestHttpClient(array_values($responses));
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
        $path = tempnam(sys_get_temp_dir(), 'azure-wi-');
        self::assertIsString($path);

        file_put_contents($path, '');
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
        $path = tempnam(sys_get_temp_dir(), 'azure-wi-');
        self::assertIsString($path);

        file_put_contents($path, 'file-assertion');
        putenv("AZURE_FEDERATED_TOKEN_FILE={$path}");

        $azureTokenBody = json_encode([
            'access_token' => 'azure-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);
        if ($azureTokenBody === false) {
            throw new \RuntimeException('Failed to encode Azure token response.');
        }

        $httpClient = $this->createHttpClient(new Response(200, ['Content-Type' => 'application/json'], $azureTokenBody));
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
}
