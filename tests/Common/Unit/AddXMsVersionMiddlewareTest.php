<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Common\Unit;

use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Middleware\AddXMsVersionMiddleware;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class AddXMsVersionMiddlewareTest extends TestCase
{
    #[Test]
    public function development_uri_uses_latest_azurite_version(): void
    {
        $request = $this->applyMiddleware(new Request(
            'GET',
            'http://127.0.0.1:10000/devstoreaccount1/container/blob',
        ));

        self::assertSame(ApiVersion::latestAzurite()->value, $request->getHeaderLine('x-ms-version'));
    }

    #[Test]
    public function production_uri_uses_latest_ga_version(): void
    {
        $request = $this->applyMiddleware(new Request(
            'GET',
            'https://account.blob.core.windows.net/container/blob',
        ));

        self::assertSame(ApiVersion::latestGA()->value, $request->getHeaderLine('x-ms-version'));
    }

    #[Test]
    public function explicitly_configured_version_takes_precedence(): void
    {
        $request = $this->applyMiddleware(
            new Request('GET', 'http://127.0.0.1:10000/devstoreaccount1/container/blob'),
            ApiVersion::V2024_08_04,
        );

        self::assertSame(ApiVersion::V2024_08_04->value, $request->getHeaderLine('x-ms-version'));
    }

    private function applyMiddleware(RequestInterface $request, ?ApiVersion $apiVersion = null): RequestInterface
    {
        $handler = static fn (RequestInterface $request, array $options): RequestInterface => $request;
        $processedRequest = (new AddXMsVersionMiddleware($apiVersion))($handler)($request, []);

        if (! $processedRequest instanceof RequestInterface) {
            self::fail('Version middleware did not return a request.');
        }

        return $processedRequest;
    }
}
