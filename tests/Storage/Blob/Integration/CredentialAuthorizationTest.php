<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Blob\Integration;

use AzureOss\Identity\ClientCertificateCredential;
use AzureOss\Identity\ClientSecretCredential;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Tests\LoadsFixtures;
use AzureOss\Tests\Storage\CreatesTempContainers;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CredentialAuthorizationTest extends TestCase
{
    use CreatesTempContainers, LoadsFixtures;

    #[Test]
    public function shared_key_credential_can_download_blob(): void
    {
        $seedService = $this->service();
        self::assertInstanceOf(StorageSharedKeyCredential::class, $seedService->credential);

        $container = $this->tempContainer('shared-key-');
        $expected = 'shared key credential works';
        $container->getBlobClient('auth.txt')->upload($expected);

        $service = new BlobServiceClient($seedService->uri, $seedService->credential);

        self::assertSame($expected, $this->downloadBlob($service, $container->containerName));
    }

    #[Test]
    public function client_secret_credential_can_download_blob(): void
    {
        $seedService = $this->service();
        $container = $this->tempContainer('client-secret-');
        $expected = 'client secret credential works';
        $container->getBlobClient('auth.txt')->upload($expected);

        $tenantId = self::getRequiredEnvironmentVariable('AZURE_TENANT_ID');
        $clientId = self::getRequiredEnvironmentVariable('AZURE_CLIENT_ID');
        $clientSecret = self::getRequiredEnvironmentVariable('AZURE_CLIENT_SECRET');

        $service = new BlobServiceClient(
            $seedService->uri,
            new ClientSecretCredential($tenantId, $clientId, $clientSecret),
        );

        self::assertSame($expected, $this->downloadBlob($service, $container->containerName));
    }

    #[Test]
    public function client_certificate_credential_can_download_blob(): void
    {
        $seedService = $this->service();
        $container = $this->tempContainer('client-certificate-');
        $expected = 'client certificate credential works';
        $container->getBlobClient('auth.txt')->upload($expected);

        $tenantId = self::getRequiredEnvironmentVariable('AZURE_TENANT_ID');
        $clientId = self::getRequiredEnvironmentVariable('AZURE_CLIENT_ID');

        $service = new BlobServiceClient(
            $seedService->uri,
            new ClientCertificateCredential(
                $tenantId,
                $clientId,
                $this->fixturePath('client-cert-pem-unencrypted.pem'),
            ),
        );

        self::assertSame($expected, $this->downloadBlob($service, $container->containerName));
    }

    private function downloadBlob(BlobServiceClient $service, string $containerName): string
    {
        return $service
            ->getContainerClient($containerName)
            ->getBlobClient('auth.txt')
            ->downloadStreaming()
            ->content
            ->getContents();
    }
}
