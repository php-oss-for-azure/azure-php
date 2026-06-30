<?php

declare(strict_types=1);

namespace AzureOss\Identity;

use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;

/**
 * Authenticates a Microsoft Entra service principal with a client certificate.
 */
final class ClientCertificateCredential implements TokenCredential
{
    /**
     * @param  string  $tenantId  Microsoft Entra tenant ID.
     * @param  string  $clientId  Application (client) ID.
     * @param  string  $clientCertificatePath  Path to PEM or PKCS#12 certificate material.
     * @param  string|null  $clientCertificatePassword  Password for encrypted certificate material.
     */
    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientCertificatePath,
        private readonly ?string $clientCertificatePassword = null,
        private readonly ClientCertificateCredentialOptions $options = new ClientCertificateCredentialOptions,
    ) {}

    public function getToken(TokenRequestContext $context): AccessToken
    {
        try {
            $client = $this->options->httpClient ?? Psr18ClientDiscovery::find();
            $requestFactory = $this->options->requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
            $streamFactory = $this->options->streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        } catch (NotFoundException $e) {
            throw new \LogicException(
                'Unable to discover a PSR-18 HTTP client and/or PSR-17 factories. '
                .'Either provide TokenCredentialOptions::$httpClient/$requestFactory/$streamFactory or install compatible implementations (e.g. guzzlehttp/guzzle + guzzlehttp/psr7).',
                previous: $e,
            );
        }

        try {
            $assertion = $this->createClientAssertion();

            $url = "https://{$this->options->authorityHost}/{$this->tenantId}/oauth2/v2.0/token";
            $request = $requestFactory
                ->createRequest('POST', $url)
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

            $body = http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                'client_assertion' => $assertion,
                'scope' => implode(' ', $context->scopes),
            ], '', '&', PHP_QUERY_RFC3986);

            $request = $request->withBody($streamFactory->createStream($body));

            $response = $client->sendRequest($request);

            if ($response->getStatusCode() !== 200) {
                $status = $response->getStatusCode();
                $body = (string) $response->getBody();
                throw new AuthenticationFailedException("Failed to authenticate with Azure. HTTP {$status}: {$body}");
            }

            return AccessToken::fromTokenResponse((string) $response->getBody());
        } catch (AuthenticationFailedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new AuthenticationFailedException('Failed to authenticate with Azure', previous: $e);
        }
    }

    private function createClientAssertion(): string
    {
        $material = $this->loadCertificateMaterial();

        $thumbprint = $this->base64UrlEncode(
            hash('sha256', $material['leafCertificateDer'], true),
        );

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'x5t#S256' => $thumbprint,
        ];

        if ($this->options->sendCertificateChain) {
            $header['x5c'] = array_map(
                fn (string $der): string => base64_encode($der),
                $material['certificateChainDer'],
            );
        }

        $tokenEndpoint = "https://{$this->options->authorityHost}/{$this->tenantId}/oauth2/v2.0/token";
        $now = time();

        $payload = [
            'aud' => $tokenEndpoint,
            'iss' => $this->clientId,
            'sub' => $this->clientId,
            'jti' => bin2hex(random_bytes(16)),
            'nbf' => $now,
            'iat' => $now,
            'exp' => $now + 600,
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $dataToSign = "{$headerEncoded}.{$payloadEncoded}";

        $signature = '';
        if (! openssl_sign($dataToSign, $signature, $material['privateKey'], OPENSSL_ALGO_SHA256)) {
            $opensslError = openssl_error_string();
            throw new \RuntimeException('Failed to sign JWT assertion: '.($opensslError !== false ? $opensslError : 'unknown error'));
        }

        if (! is_string($signature)) {
            throw new \RuntimeException('Failed to sign JWT assertion: invalid signature format');
        }

        $signatureEncoded = $this->base64UrlEncode($signature);

        return "{$dataToSign}.{$signatureEncoded}";
    }

    /**
     * @return array{
     *     privateKey: \OpenSSLAsymmetricKey,
     *     leafCertificateDer: string,
     *     certificateChainDer: list<string>
     * }
     */
    private function loadPkcs12CertificateMaterial(string $pkcs12Contents): array
    {
        $pkcs12Data = [];
        if (! openssl_pkcs12_read($pkcs12Contents, $pkcs12Data, $this->clientCertificatePassword ?? '')) {
            throw new \RuntimeException(
                'Unable to decrypt private key. The passphrase may be incorrect or the certificate file is invalid.',
            );
        }

        if (! is_array($pkcs12Data)) {
            throw new \RuntimeException('Unable to parse the PKCS#12 file');
        }

        /** @var array{cert?: string, pkey?: string, extracerts?: list<string>} $pkcs12Data */
        $leafCertPem = $pkcs12Data['cert'] ?? null;
        if (! is_string($leafCertPem)) {
            throw new \RuntimeException('Unable to parse the certificate from the PKCS#12 file');
        }

        $privateKeyPem = $pkcs12Data['pkey'] ?? null;
        if (! is_string($privateKeyPem)) {
            throw new \RuntimeException(
                'Unable to decrypt private key. The passphrase may be incorrect or the certificate file is invalid.',
            );
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new \RuntimeException('Unable to load private key from PKCS#12 file');
        }

        $this->assertRsaKey($privateKey);

        $certificateChainDer = $this->parseCertificateDerFromPem($leafCertPem);

        $extraCerts = $pkcs12Data['extracerts'] ?? null;
        if (is_array($extraCerts)) {
            foreach ($extraCerts as $extraCertPem) {
                $certificateChainDer = array_merge(
                    $certificateChainDer,
                    $this->parseCertificateDerFromPem($extraCertPem),
                );
            }
        }

        if ($certificateChainDer === []) {
            throw new \RuntimeException('Unable to parse the certificate from the PKCS#12 file');
        }

        return [
            'privateKey' => $privateKey,
            'leafCertificateDer' => $certificateChainDer[0],
            'certificateChainDer' => $certificateChainDer,
        ];
    }

    /**
     * @return array{
     *     privateKey: \OpenSSLAsymmetricKey,
     *     leafCertificateDer: string,
     *     certificateChainDer: list<string>
     * }
     */
    private function loadCertificateMaterial(): array
    {
        $contents = @file_get_contents($this->clientCertificatePath);
        if ($contents === false) {
            throw new \RuntimeException("Unable to read certificate file: {$this->clientCertificatePath}");
        }

        if (preg_match('/-----BEGIN (CERTIFICATE|PRIVATE KEY|RSA PRIVATE KEY|ENCRYPTED PRIVATE KEY)-----/', $contents) === 1) {
            return $this->loadPemCertificateMaterial($contents);
        }

        return $this->loadPkcs12CertificateMaterial($contents);
    }

    /**
     * @return array{
     *     privateKey: \OpenSSLAsymmetricKey,
     *     leafCertificateDer: string,
     *     certificateChainDer: list<string>
     * }
     */
    private function loadPemCertificateMaterial(string $pemContents): array
    {
        $password = $this->clientCertificatePassword ?? '';

        $privateKey = openssl_pkey_get_private($pemContents, $password);
        if ($privateKey === false) {
            throw new \RuntimeException(
                'Unable to decrypt private key. The passphrase may be incorrect or the certificate file is invalid.',
            );
        }

        $this->assertRsaKey($privateKey);

        $certificateChainDer = $this->parseCertificateDerFromPem($pemContents);
        if ($certificateChainDer === []) {
            throw new \RuntimeException('Unable to parse the certificate from the PEM file');
        }

        return [
            'privateKey' => $privateKey,
            'leafCertificateDer' => $certificateChainDer[0],
            'certificateChainDer' => $certificateChainDer,
        ];
    }

    /**
     * Parse PEM content and return DER-encoded bytes for each certificate found.
     *
     * @return list<string>
     */
    private function parseCertificateDerFromPem(string $pemContents): array
    {
        $certificates = [];

        $matches = [];
        preg_match_all(
            '/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s',
            $pemContents,
            $matches,
        );

        foreach ($matches[1] as $base64Content) {
            $stripped = preg_replace('/\s+/', '', $base64Content);
            if (! is_string($stripped)) {
                throw new \RuntimeException('Failed to process certificate data');
            }

            $der = base64_decode($stripped, true);
            if ($der === false || $der === '') {
                throw new \RuntimeException('Failed to decode certificate DER data');
            }

            $reconstructedPem = "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE-----\n";
            $parsed = openssl_x509_parse($reconstructedPem);
            if ($parsed === false) {
                throw new \RuntimeException('Unable to parse a certificate from the PEM file');
            }

            $certificates[] = $der;
        }

        return $certificates;
    }

    private function assertRsaKey(\OpenSSLAsymmetricKey $key): void
    {
        $details = openssl_pkey_get_details($key);
        if ($details === false) {
            throw new \RuntimeException('Unable to read key details');
        }

        if (($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new \RuntimeException('Only RSA keys are supported for client certificate authentication');
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
