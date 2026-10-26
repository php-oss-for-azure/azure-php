<?php

declare(strict_types=1);

namespace AzureOss\Identity;

/**
 * Represents an OAuth access token and its expiry information.
 */
final class AccessToken
{
    /**
     * @param  string  $token  Token value sent in the Authorization header.
     * @param  \DateTimeInterface  $expiresOn  Instant at which the token expires.
     * @param  string  $tokenType  Authorization scheme returned by Microsoft Entra ID.
     */
    public function __construct(
        public readonly string $token,
        public readonly \DateTimeInterface $expiresOn,
        public readonly string $tokenType
    ) {}

    /**
     * Creates an access token from a Microsoft Entra token endpoint response.
     *
     * @internal
     */
    public static function fromTokenResponse(string $responseBody): self
    {
        $data = json_decode($responseBody, true);

        if (! is_array($data) || ! array_key_exists('access_token', $data) || ! is_string($data['access_token'])) {
            throw new \RuntimeException('Unexpected response from Azure');
        }

        $expiresOn = null;
        if (array_key_exists('expires_in', $data) && is_numeric($data['expires_in'])) {
            $expiresOn = (new \DateTimeImmutable)->setTimestamp(time() + (int) $data['expires_in']);
        } elseif (array_key_exists('expires_on', $data)) {
            $rawExpiresOn = $data['expires_on'];
            if (is_int($rawExpiresOn) || is_float($rawExpiresOn) || is_string($rawExpiresOn)) {
                $expiresOn = self::parseExpiresOn($rawExpiresOn);
            }
        }

        if (! $expiresOn instanceof \DateTimeInterface ||
            ! array_key_exists('token_type', $data) ||
            ! is_string($data['token_type'])
        ) {
            throw new \RuntimeException('Unexpected response from Azure');
        }

        return new self(
            $data['access_token'],
            $expiresOn,
            $data['token_type'],
        );
    }

    private static function parseExpiresOn(string|int|float $expiresOn): \DateTimeImmutable
    {
        if (is_numeric($expiresOn)) {
            return (new \DateTimeImmutable)->setTimestamp((int) $expiresOn);
        }

        $expiresOn = trim($expiresOn);
        if ($expiresOn === '') {
            throw new \RuntimeException('Unexpected response from Azure');
        }

        if (is_numeric($expiresOn)) {
            return (new \DateTimeImmutable)->setTimestamp((int) $expiresOn);
        }

        $date = new \DateTimeImmutable($expiresOn);

        return $date;
    }
}
