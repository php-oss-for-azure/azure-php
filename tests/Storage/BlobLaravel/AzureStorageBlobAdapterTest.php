<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\BlobLaravel;

use AzureOss\Storage\BlobLaravel\AzureStorageBlobAdapter;
use AzureOss\Storage\BlobLaravel\AzureStorageBlobServiceProvider;
use AzureOss\Storage\Tests\CreatesTempContainers;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AzureStorageBlobAdapterTest extends TestCase
{
    use CreatesTempContainers;

    protected function getPackageProviders($app): array
    {
        return [AzureStorageBlobServiceProvider::class];
    }

    #[Test]
    public function it_resolves_from_manager(): void
    {
        $container = $this->service()->getContainerClient('noop');

        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => getenv(
                    'AZURE_STORAGE_CONNECTION_STRING',
                ),
                'container' => $container->containerName,
            ],
        ]);

        self::assertInstanceOf(
            AzureStorageBlobAdapter::class,
            Storage::disk('azure'),
        );
    }

    #[Test]
    public function url_uses_sas_by_default_when_using_connection_string(): void
    {
        $container = $this->tempContainer('laravel-');

        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => getenv(
                    'AZURE_STORAGE_CONNECTION_STRING',
                ),
                'container' => $container->containerName,
            ],
        ]);

        /** @phpstan-ignore-next-line */
        $url = Storage::disk('azure')->url('file.txt');
        self::assertIsString($url);
        self::assertStringContainsString('sig=', $url);
    }

    #[Test]
    public function url_uses_direct_public_url_when_is_public_container_is_enabled(): void
    {
        $container = $this->tempContainer('laravel-', public: true);

        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => getenv(
                    'AZURE_STORAGE_CONNECTION_STRING_PUBLIC',
                ),
                'container' => $container->containerName,
                'is_public_container' => true,
            ],
        ]);

        /** @phpstan-ignore-next-line */
        $url = Storage::disk('azure')->url('file.txt');
        self::assertIsString($url);
        self::assertStringNotContainsString('sig=', $url);
    }

    #[Test]
    public function url_uses_configured_url_with_the_disk_prefix(): void
    {
        config([
            'filesystems.disks.azure-custom-url' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://127.0.0.1:10000/devstoreaccount1;',
                'container' => 'test-container',
                'prefix' => 'tenant',
                'url' => 'https://cdn.example.com/assets/',
            ],
        ]);

        $disk = Storage::disk('azure-custom-url');
        self::assertInstanceOf(AzureStorageBlobAdapter::class, $disk);

        $url = $disk->url('/file.txt');

        self::assertSame('https://cdn.example.com/assets/tenant/file.txt', $url);
    }

    #[Test]
    public function temporary_url_replaces_the_origin_for_downloads_and_uploads(): void
    {
        config([
            'filesystems.disks.azure-custom-temporary-url' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://127.0.0.1:10000/devstoreaccount1;',
                'container' => 'test-container',
                'prefix' => 'tenant',
                'temporary_url' => 'https://cdn.example.com:8443',
            ],
        ]);

        $disk = Storage::disk('azure-custom-temporary-url');
        self::assertInstanceOf(AzureStorageBlobAdapter::class, $disk);

        $downloadUrl = $disk->temporaryUrl('file.txt', now()->addMinute());
        $upload = $disk->temporaryUploadUrl('upload.txt', now()->addMinute());
        self::assertArrayHasKey('url', $upload);
        self::assertIsString($upload['url']);

        self::assertSame('https', parse_url($downloadUrl, PHP_URL_SCHEME));
        self::assertSame('cdn.example.com', parse_url($downloadUrl, PHP_URL_HOST));
        self::assertSame(8443, parse_url($downloadUrl, PHP_URL_PORT));
        self::assertSame('/devstoreaccount1/test-container/tenant/file.txt', parse_url($downloadUrl, PHP_URL_PATH));
        self::assertStringContainsString('sig=', $downloadUrl);

        self::assertSame('https', parse_url($upload['url'], PHP_URL_SCHEME));
        self::assertSame('cdn.example.com', parse_url($upload['url'], PHP_URL_HOST));
        self::assertSame(8443, parse_url($upload['url'], PHP_URL_PORT));
        self::assertSame('/devstoreaccount1/test-container/tenant/upload.txt', parse_url($upload['url'], PHP_URL_PATH));
        self::assertStringContainsString('sig=', $upload['url']);
    }

    #[Test]
    public function driver_works_with_connection_string(): void
    {
        $container = $this->tempContainer('laravel-');

        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => getenv(
                    'AZURE_STORAGE_CONNECTION_STRING',
                ),
                'container' => $container->containerName,
            ],
        ]);

        $driver = Storage::disk('azure');

        $driver->deleteDirectory('');
        self::assertFalse($driver->exists('file.text'));

        $driver->put('file.txt', 'content');
        self::assertTrue($driver->exists('file.txt'));
        self::assertEquals('content', $driver->get('file.txt'));

        $driver->put('cache-control.txt', 'content', [
            'httpHeaders' => [
                'cacheControl' => 'public, max-age=31536000',
            ],
        ]);
        $properties = $container
            ->getBlobClient('cache-control.txt')
            ->getProperties();
        self::assertSame('public, max-age=31536000', $properties->cacheControl);

        /** @phpstan-ignore-next-line */
        $temporaryUrl = $driver->temporaryUrl('file.txt', now()->addMinute());
        self::assertIsString($temporaryUrl);
        self::assertEquals(
            'content',
            self::httpResponse(Http::get($temporaryUrl))->body(),
        );

        /** @phpstan-ignore-next-line */
        $temporaryUrlWithHeaders = $driver->temporaryUrl(
            'file.txt',
            now()->addMinute(),
            [
                'httpHeaders' => [
                    'contentDisposition' => 'attachment; filename="file.txt"',
                    'contentType' => 'text/plain',
                ],
            ],
        );
        self::assertIsString($temporaryUrlWithHeaders);

        $temporaryUrlResponse = self::httpResponse(
            Http::get($temporaryUrlWithHeaders),
        );
        self::assertTrue($temporaryUrlResponse->successful());
        self::assertSame(
            'attachment; filename="file.txt"',
            $temporaryUrlResponse->header('Content-Disposition'),
        );
        self::assertStringStartsWith(
            'text/plain',
            $temporaryUrlResponse->header('Content-Type'),
        );

        /** @phpstan-ignore-next-line */
        $url = $driver->url('file.txt');
        self::assertIsString($url);
        self::assertEquals(
            'content',
            self::httpResponse(Http::get($url))->body(),
        );

        $driver->copy('file.txt', 'file2.txt');
        self::assertTrue($driver->exists('file2.txt'));

        $driver->move('file2.txt', 'file3.txt');
        self::assertFalse($driver->exists('file2.txt'));
        self::assertTrue($driver->exists('file3.txt'));

        /** @phpstan-ignore-next-line */
        $uploadData = $driver->temporaryUploadUrl(
            'temp-upload-test.txt',
            now()->addMinutes(5),
            [
                'content-type' => 'text/plain',
            ],
        );
        self::assertIsArray($uploadData);
        self::assertIsString($uploadData['url']);
        self::assertIsArray($uploadData['headers']);

        $content = 'This content was uploaded directly to a temporary URL';
        $response = Http::withHeaders($uploadData['headers'])
            ->withBody($content, 'text/plain')
            ->put($uploadData['url']);
        self::assertTrue($response->successful());

        self::assertTrue($driver->exists('temp-upload-test.txt'));
        self::assertEquals($content, $driver->get('temp-upload-test.txt'));

        self::assertCount(4, $driver->allFiles());
        $driver->deleteDirectory('');
        self::assertCount(0, $driver->allFiles());
    }

    #[Test]
    public function driver_works_with_token(): void
    {
        $endpoint = getenv('AZURE_STORAGE_BLOB_ENDPOINT');
        $accountName = getenv('AZURE_STORAGE_BLOB_ACCOUNT_NAME');
        $tenantId = getenv('AZURE_STORAGE_BLOB_TENANT_ID');
        $clientId = getenv('AZURE_STORAGE_BLOB_CLIENT_ID');
        $clientSecret = getenv('AZURE_STORAGE_BLOB_CLIENT_SECRET');

        $hasEndpoint = is_string($endpoint) && $endpoint !== '';
        $hasAccountName = is_string($accountName) && $accountName !== '';

        if (! $hasEndpoint && ! $hasAccountName) {
            self::markTestSkipped(
                'AZURE_STORAGE_BLOB_ENDPOINT or AZURE_STORAGE_BLOB_ACCOUNT_NAME is required.',
            );
        }

        if (
            ! is_string($tenantId) ||
            ! is_string($clientId) ||
            ! is_string($clientSecret)
        ) {
            self::markTestSkipped(
                'AZURE_STORAGE_BLOB_TENANT_ID, AZURE_STORAGE_BLOB_CLIENT_ID, AZURE_STORAGE_BLOB_CLIENT_SECRET are required.',
            );
        }

        $container = $this->tempContainer('laravel-');

        $diskConfig = [
            'driver' => 'azure-storage-blob',
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'container' => $container->containerName,
        ];
        if ($hasEndpoint) {
            $diskConfig['endpoint'] = $endpoint;
        } else {
            $diskConfig['account_name'] = $accountName;
        }

        config(['filesystems.disks.azure' => $diskConfig]);

        $driver = Storage::disk('azure');
        self::assertInstanceOf(AzureStorageBlobAdapter::class, $driver);

        $driver->deleteDirectory('');
        self::assertFalse($driver->exists('token-test.txt'));

        $driver->put('token-test.txt', 'token auth content');
        self::assertTrue($driver->exists('token-test.txt'));
        self::assertEquals(
            'token auth content',
            $driver->get('token-test.txt'),
        );

        self::assertFalse($driver->providesTemporaryUrls());

        $driver->delete('token-test.txt');
        self::assertFalse($driver->exists('token-test.txt'));
    }

    #[Test]
    public function it_throws_when_no_credentials_provided(): void
    {
        config([
            'filesystems.disks.azure-invalid' => [
                'driver' => 'azure-storage-blob',
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Either [connection_string] or [endpoint/account_name] must be provided',
        );

        Storage::disk('azure-invalid');
    }

    #[Test]
    public function it_throws_when_both_connection_string_and_token_credentials_provided(): void
    {
        config([
            'filesystems.disks.azure-both' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=https;AccountName=test;AccountKey=key;EndpointSuffix=core.windows.net',
                'endpoint' => 'https://test.blob.core.windows.net',
                'tenant_id' => 'tenant',
                'client_id' => 'client',
                'client_secret' => 'secret',
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Cannot use both [connection_string] and token-based credentials',
        );

        Storage::disk('azure-both');
    }

    #[Test]
    public function it_throws_when_token_credentials_missing_endpoint_and_account_name(): void
    {
        config([
            'filesystems.disks.azure-token-missing-endpoint' => [
                'driver' => 'azure-storage-blob',
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Either [connection_string] or [endpoint/account_name] must be provided',
        );

        Storage::disk('azure-token-missing-endpoint');
    }

    #[Test]
    public function it_throws_when_token_credentials_cannot_be_inferred(): void
    {
        config([
            'filesystems.disks.azure-infer-fail' => [
                'driver' => 'azure-storage-blob',
                'endpoint' => 'https://test.blob.core.windows.net',
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The [credential] must be provided in the disk configuration when not using [connection_string].',
        );

        Storage::disk('azure-infer-fail');
    }

    #[Test]
    public function it_resolves_with_managed_identity_credential_without_explicit_ids(): void
    {
        config([
            'filesystems.disks.azure-mi' => [
                'driver' => 'azure-storage-blob',
                'credential' => 'managed_identity',
                'endpoint' => 'https://test.blob.core.windows.net',
                'client_id' => 'user-assigned-mi-client-id',
                'container' => 'test-container',
            ],
        ]);

        self::assertInstanceOf(
            AzureStorageBlobAdapter::class,
            Storage::disk('azure-mi'),
        );
    }

    #[Test]
    public function it_resolves_with_system_assigned_managed_identity(): void
    {
        config([
            'filesystems.disks.azure-mi-system' => [
                'driver' => 'azure-storage-blob',
                'credential' => 'managed_identity',
                'endpoint' => 'https://test.blob.core.windows.net',
                'container' => 'test-container',
            ],
        ]);

        self::assertInstanceOf(
            AzureStorageBlobAdapter::class,
            Storage::disk('azure-mi-system'),
        );
    }

    #[Test]
    public function it_resolves_with_workload_identity_credential_without_explicit_ids(): void
    {
        config([
            'filesystems.disks.azure-wi' => [
                'driver' => 'azure-storage-blob',
                'credential' => 'workload_identity',
                'endpoint' => 'https://test.blob.core.windows.net',
                'tenant_id' => 'tenant',
                'client_id' => 'client',
                'container' => 'test-container',
            ],
        ]);

        self::assertInstanceOf(
            AzureStorageBlobAdapter::class,
            Storage::disk('azure-wi'),
        );
    }

    #[Test]
    public function it_is_backwards_compatible_with_client_secret_when_credential_is_omitted(): void
    {
        config([
            'filesystems.disks.azure-legacy-client-secret' => [
                'driver' => 'azure-storage-blob',
                'endpoint' => 'https://test.blob.core.windows.net',
                'tenant_id' => 'tenant',
                'client_id' => 'client',
                'client_secret' => 'secret',
                'container' => 'test-container',
            ],
        ]);

        self::assertInstanceOf(
            AzureStorageBlobAdapter::class,
            Storage::disk('azure-legacy-client-secret'),
        );
    }

    #[Test]
    public function it_resolves_with_client_secret_credential(): void
    {
        config([
            'filesystems.disks.azure-client-secret' => [
                'driver' => 'azure-storage-blob',
                'credential' => 'client_secret',
                'endpoint' => 'https://test.blob.core.windows.net',
                'tenant_id' => 'tenant',
                'client_id' => 'client',
                'client_secret' => 'secret',
                'container' => 'test-container',
            ],
        ]);

        self::assertInstanceOf(
            AzureStorageBlobAdapter::class,
            Storage::disk('azure-client-secret'),
        );
    }

    #[Test]
    public function it_resolves_with_shared_key_credential_using_account_key_option(): void
    {
        config([
            'filesystems.disks.azure-shared-key' => [
                'driver' => 'azure-storage-blob',
                'credential' => 'shared_key',
                'account_name' => 'testaccount',
                'account_key' => 'bXlrZXk=', // base64 for "mykey"
                'endpoint_suffix' => 'example.invalid',
                'container' => 'test-container',
            ],
        ]);

        $disk = Storage::disk('azure-shared-key');
        self::assertInstanceOf(AzureStorageBlobAdapter::class, $disk);
        self::assertTrue($disk->providesTemporaryUrls());
    }

    #[Test]
    public function it_builds_blob_endpoint_using_default_endpoint_suffix_when_missing(): void
    {
        config([
            'filesystems.disks.azure-shared-key-default-suffix' => [
                'driver' => 'azure-storage-blob',
                'credential' => 'shared_key',
                'account_name' => 'testaccount',
                'account_key' => 'bXlrZXk=',
                'container' => 'test-container',
            ],
        ]);

        $disk = Storage::disk('azure-shared-key-default-suffix');
        self::assertInstanceOf(AzureStorageBlobAdapter::class, $disk);
    }

    #[Test]
    public function it_throws_when_shared_key_missing_account_key(): void
    {
        config([
            'filesystems.disks.azure-shared-key-missing-key' => [
                'driver' => 'azure-storage-blob',
                'credential' => 'shared_key',
                'account_name' => 'testaccount',
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The [shared_key] credential requires [account_name] and [account_key].',
        );

        Storage::disk('azure-shared-key-missing-key');
    }

    #[Test]
    public function it_throws_when_endpoint_missing_and_account_name_is_empty_string(): void
    {
        config([
            'filesystems.disks.azure-empty-account-name' => [
                'driver' => 'azure-storage-blob',
                'credential' => 'managed_identity',
                'account_name' => '',
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Either [endpoint] or [account_name] must be provided for token-based credentials.',
        );

        Storage::disk('azure-empty-account-name');
    }

    #[Test]
    public function it_resolves_with_client_certificate_credential(): void
    {
        config([
            'filesystems.disks.azure-client-certificate' => [
                'driver' => 'azure-storage-blob',
                'credential' => 'client_certificate',
                'endpoint' => 'https://test.blob.core.windows.net',
                'tenant_id' => 'tenant',
                'client_id' => 'client',
                'client_certificate_path' => '/path/to/cert.pem',
                'container' => 'test-container',
            ],
        ]);

        $disk = Storage::disk('azure-client-certificate');
        self::assertInstanceOf(AzureStorageBlobAdapter::class, $disk);
        self::assertFalse($disk->providesTemporaryUrls());
    }

    #[Test]
    public function it_throws_when_container_missing(): void
    {
        config([
            'filesystems.disks.azure-no-container' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=https;AccountName=test;AccountKey=key;EndpointSuffix=core.windows.net',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [container] must be a string');

        Storage::disk('azure-no-container');
    }

    #[Test]
    public function it_throws_when_container_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=https;AccountName=test;AccountKey=key;EndpointSuffix=core.windows.net',
                'container' => ['invalid'],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [container] must be a string');

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_prefix_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=https;AccountName=test;AccountKey=key;EndpointSuffix=core.windows.net',
                'container' => 'test-container',
                'prefix' => ['invalid'],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [prefix] must be a string');

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_root_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=https;AccountName=test;AccountKey=key;EndpointSuffix=core.windows.net',
                'container' => 'test-container',
                'root' => ['invalid'],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [root] must be a string');

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_connection_string_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => ['invalid'],
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The [connection_string] must be a string',
        );

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_tenant_id_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'endpoint' => 'https://test.blob.core.windows.net',
                'tenant_id' => ['invalid'],
                'client_id' => 'client',
                'client_secret' => 'secret',
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [tenant_id] must be a string');

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_client_id_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'endpoint' => 'https://test.blob.core.windows.net',
                'tenant_id' => 'tenant',
                'client_id' => ['invalid'],
                'client_secret' => 'secret',
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [client_id] must be a string');

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_client_secret_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'endpoint' => 'https://test.blob.core.windows.net',
                'tenant_id' => 'tenant',
                'client_id' => 'client',
                'client_secret' => ['invalid'],
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [client_secret] must be a string');

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_endpoint_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'endpoint' => ['invalid'],
                'tenant_id' => 'tenant',
                'client_id' => 'client',
                'client_secret' => 'secret',
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [endpoint] must be a string');

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_endpoint_suffix_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'account_name' => 'testaccount',
                'endpoint_suffix' => ['invalid'],
                'credential' => 'managed_identity',
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [endpoint_suffix] must be a string');

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_account_name_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'account_name' => ['invalid'],
                'credential' => 'managed_identity',
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [account_name] must be a string');

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_is_public_container_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=https;AccountName=test;AccountKey=key;EndpointSuffix=core.windows.net',
                'container' => 'test-container',
                'is_public_container' => 'true',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The [is_public_container] must be a boolean',
        );

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_url_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=https;AccountName=test;AccountKey=key;EndpointSuffix=core.windows.net',
                'container' => 'test-container',
                'url' => ['invalid'],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [url] must be a string');

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_temporary_url_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=https;AccountName=test;AccountKey=key;EndpointSuffix=core.windows.net',
                'container' => 'test-container',
                'temporary_url' => ['invalid'],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [temporary_url] must be a string');

        Storage::disk('azure');
    }

    #[Test]
    public function it_resolves_the_disk_when_http_client_options_are_configured(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://127.0.0.1:10000/devstoreaccount1;',
                'container' => 'noop',
                'timeout' => 30,
                'connect_timeout' => 10,
                'verify_ssl' => false,
            ],
        ]);

        self::assertInstanceOf(
            AzureStorageBlobAdapter::class,
            Storage::disk('azure'),
        );
    }

    #[Test]
    public function it_rejects_a_non_integer_timeout(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://127.0.0.1:10000/devstoreaccount1;',
                'container' => 'noop',
                'timeout' => '30',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('[timeout]');

        Storage::disk('azure');
    }

    #[Test]
    public function it_rejects_a_non_boolean_verify_ssl(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'connection_string' => 'DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://127.0.0.1:10000/devstoreaccount1;',
                'container' => 'noop',
                'verify_ssl' => 'yes',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('[verify_ssl]');

        Storage::disk('azure');
    }

    #[Test]
    public function it_throws_when_credential_has_wrong_type(): void
    {
        config([
            'filesystems.disks.azure' => [
                'driver' => 'azure-storage-blob',
                'credential' => ['invalid'],
                'endpoint' => 'https://test.blob.core.windows.net',
                'container' => 'test-container',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The [credential] must be a string');

        Storage::disk('azure');
    }

    private static function httpResponse(
        Response|PromiseInterface $response,
    ): Response {
        if ($response instanceof Response) {
            return $response;
        }

        $resolved = $response->wait();

        if ($resolved instanceof Response) {
            return $resolved;
        }

        self::fail('Expected Laravel HTTP response.');
    }
}
