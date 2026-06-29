<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage;

use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Models\CreateContainerOptions;
use AzureOss\Storage\Blob\Models\PublicAccessType;
use AzureOss\Tests\RequiresEnvironmentVariables;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

/** @mixin TestCase */
trait CreatesTempContainers
{
    use RequiresEnvironmentVariables;

    /** @var list<BlobContainerClient> */
    private array $tempContainers = [];

    protected function service(
        bool $versions = false,
        bool $public = false,
        bool $softDeletes = false,
    ): BlobServiceClient {
        return BlobServiceClient::fromConnectionString($this->resolveConnectionString(
            $versions,
            $public,
            $softDeletes,
        ));
    }

    protected function tempContainer(
        string $prefix = 'test-',
        bool $versions = false,
        bool $public = false,
        bool $softDeletes = false,
    ): BlobContainerClient {
        $serviceClient = $this->service($versions, $public, $softDeletes);
        $containerClient = $serviceClient->getContainerClient($prefix.bin2hex(random_bytes(12)));

        $containerClient->create(new CreateContainerOptions(
            $public ? PublicAccessType::BLOB : PublicAccessType::NONE,
        ));

        $this->tempContainers[] = $containerClient;

        return $containerClient;
    }

    #[After]
    protected function cleanupTempContainers(): void
    {
        foreach ($this->tempContainers as $containerClient) {
            try {
                $containerClient->delete();
            } catch (\Throwable) {
            }
        }

        $this->tempContainers = [];
    }

    /**
     * @return array<string, string>
     */
    private static function envMap(): array
    {
        return [
            'default' => 'AZURE_STORAGE_CONNECTION_STRING',
            'public' => 'AZURE_STORAGE_CONNECTION_STRING_PUBLIC',
            'soft-deletes' => 'AZURE_STORAGE_CONNECTION_STRING_SOFT_DELETES',
            'versions' => 'AZURE_STORAGE_CONNECTION_STRING_VERSIONS',
            'soft-deletes+versions' => 'AZURE_STORAGE_CONNECTION_STRING_SOFT_DELETES_VERSIONS',
        ];
    }

    private function resolveConnectionString(bool $versions, bool $public, bool $softDeletes): string
    {
        $parts = [];

        if ($public) {
            $parts[] = 'public';
        }
        if ($softDeletes) {
            $parts[] = 'soft-deletes';
        }
        if ($versions) {
            $parts[] = 'versions';
        }

        $key = $parts === [] ? 'default' : implode('+', $parts);

        $map = self::envMap();
        $envVar = $map[$key]
            ?? throw new \RuntimeException("No storage account configured for scenario: {$key}");

        return self::getRequiredEnvironmentVariable($envVar);
    }
}
