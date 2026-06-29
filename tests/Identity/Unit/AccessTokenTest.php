<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity\Unit;

use AzureOss\Identity\AccessToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AccessTokenTest extends TestCase
{
    #[Test]
    public function from_token_response_accepts_expires_in(): void
    {
        $token = AccessToken::fromTokenResponse(json_encode([
            'access_token' => 'token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], JSON_THROW_ON_ERROR));

        self::assertSame('token', $token->token);
        self::assertSame('Bearer', $token->tokenType);
        self::assertGreaterThan((new \DateTimeImmutable)->getTimestamp(), $token->expiresOn->getTimestamp());
    }

    #[Test]
    public function from_token_response_accepts_expires_on_epoch(): void
    {
        $token = AccessToken::fromTokenResponse(json_encode([
            'access_token' => 'token',
            'expires_on' => '1700000000',
            'token_type' => 'Bearer',
        ], JSON_THROW_ON_ERROR));

        self::assertSame(1700000000, $token->expiresOn->getTimestamp());
    }

    #[Test]
    public function from_token_response_throws_when_expiration_is_missing(): void
    {
        $this->expectException(\RuntimeException::class);

        AccessToken::fromTokenResponse(json_encode([
            'access_token' => 'token',
            'token_type' => 'Bearer',
        ], JSON_THROW_ON_ERROR));
    }
}
