<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity\Unit;

use AzureOss\Identity\AccessToken;
use AzureOss\Identity\AggregateException;
use AzureOss\Identity\ChainedTokenCredential;
use AzureOss\Identity\CredentialUnavailableException;
use AzureOss\Identity\TokenCredential;
use AzureOss\Identity\TokenRequestContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChainedTokenCredentialTest extends TestCase
{
    #[Test]
    public function returns_the_first_available_token(): void
    {
        $expected = new AccessToken('token', new \DateTimeImmutable('+1 hour'), 'Bearer');

        $credential = new ChainedTokenCredential([
            new class implements TokenCredential
            {
                public function getToken(TokenRequestContext $context): never
                {
                    throw new CredentialUnavailableException('first');
                }
            },
            new class($expected) implements TokenCredential
            {
                public function __construct(private AccessToken $token) {}

                public function getToken(TokenRequestContext $context): AccessToken
                {
                    return $this->token;
                }
            },
        ]);

        self::assertSame($expected, $credential->getToken(new TokenRequestContext(['scope'])));
    }

    #[Test]
    public function aggregates_credential_unavailable_exceptions_when_all_sources_are_unavailable(): void
    {
        $credential = new ChainedTokenCredential([
            new class implements TokenCredential
            {
                public function getToken(TokenRequestContext $context): never
                {
                    throw new CredentialUnavailableException('first');
                }
            },
            new class implements TokenCredential
            {
                public function getToken(TokenRequestContext $context): never
                {
                    throw new CredentialUnavailableException('second');
                }
            },
        ]);

        try {
            $credential->getToken(new TokenRequestContext(['scope']));
            self::fail('Expected exception not thrown.');
        } catch (CredentialUnavailableException $e) {
            self::assertStringContainsString("- first\n- second", $e->getMessage());
            self::assertInstanceOf(AggregateException::class, $e->getPrevious());

            /** @var AggregateException $previous */
            $previous = $e->getPrevious();
            self::assertCount(2, $previous->exceptions);
        }
    }

    #[Test]
    public function throws_credential_unavailable_when_the_chain_is_empty(): void
    {
        $this->expectException(CredentialUnavailableException::class);
        $this->expectExceptionMessage('No credential available.');

        (new ChainedTokenCredential)->getToken(new TokenRequestContext(['scope']));
    }
}
