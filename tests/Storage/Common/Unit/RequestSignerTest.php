<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Common\Unit;

use AzureOss\Identity\AccessToken;
use AzureOss\Identity\TokenCredential;
use AzureOss\Identity\TokenRequestContext;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Middleware\AddEntraIdAuthorizationHeaderMiddleware;
use AzureOss\Storage\Common\Middleware\AddSharedKeyAuthorizationHeaderMiddleware;
use AzureOss\Storage\Common\Middleware\ClientFactory;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestSignerTest extends TestCase
{
    private const DEVSTORE_ACCOUNT_KEY = 'Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==';

    #[Test]
    public function shared_key_signer_matches_known_signature(): void
    {
        $signer = new AddSharedKeyAuthorizationHeaderMiddleware(new StorageSharedKeyCredential('devstoreaccount1', self::DEVSTORE_ACCOUNT_KEY));

        // String to sign: "DELETE" and 12 newlines (11 empty standard headers, Content-Length 0 signs as empty), then
        // "x-ms-date:Wed, 23 Sep 2026 00:00:00 GMT\nx-ms-delete-snapshots:include\nx-ms-version:2025-11-05\n"
        // and "/devstoreaccount1/devstoreaccount1/container/blob.txt\ntimeout:30".
        $signed = $signer->sign(new Request('DELETE', 'http://127.0.0.1:10000/devstoreaccount1/container/blob.txt?timeout=30', [
            'x-ms-version' => '2025-11-05',
            'x-ms-date' => 'Wed, 23 Sep 2026 00:00:00 GMT',
            'x-ms-delete-snapshots' => 'include',
            'Content-Length' => '0',
        ]));

        self::assertSame(
            'SharedKey devstoreaccount1:hStsM1z1j+rz9H0bI+OTGmLESQp9VFVm4G22Pq4ZEuM=',
            $signed->getHeaderLine('Authorization'),
        );
    }

    #[Test]
    public function entra_id_signer_reuses_token(): void
    {
        $credential = self::countingTokenCredential();
        $signer = new AddEntraIdAuthorizationHeaderMiddleware($credential);

        $first = $signer->sign(new Request('DELETE', 'https://account.blob.core.windows.net/container/a'));
        $second = $signer->sign(new Request('DELETE', 'https://account.blob.core.windows.net/container/b'));

        self::assertSame('Bearer token', $first->getHeaderLine('Authorization'));
        self::assertSame('Bearer token', $second->getHeaderLine('Authorization'));
        self::assertSame(1, $credential->calls);
    }

    #[Test]
    public function client_factory_creates_signer_for_credential(): void
    {
        $factory = new ClientFactory;
        $tokenCredential = self::createStub(TokenCredential::class);

        self::assertInstanceOf(AddSharedKeyAuthorizationHeaderMiddleware::class, $factory->createRequestSigner(new StorageSharedKeyCredential('account', base64_encode('key'))));
        self::assertInstanceOf(AddEntraIdAuthorizationHeaderMiddleware::class, $factory->createRequestSigner($tokenCredential));
        self::assertNull($factory->createRequestSigner(null));
    }

    /**
     * @return TokenCredential&object{calls: int}
     */
    private static function countingTokenCredential(): TokenCredential
    {
        return new class implements TokenCredential
        {
            public int $calls = 0;

            public function getToken(TokenRequestContext $context): AccessToken
            {
                $this->calls++;

                return new AccessToken('token', new \DateTimeImmutable('+1 hour'), 'Bearer');
            }
        };
    }
}
