<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\BlobFlysystem;

use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter;
use AzureOss\Tests\RequiresEnvironmentVariables;
use GuzzleHttp\Psr7\Query;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use PHPUnit\Framework\Attributes\Test;

class AzureBlobStorageTest extends FilesystemAdapterTestCase
{
    use RequiresEnvironmentVariables;

    private static string $containerName;

    protected function runScenario(callable $scenario): void
    {
        $scenario(); // disable retries
    }

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');

        if (! is_string($connectionString)) {
            self::fail('AZURE_STORAGE_CONNECTION_STRING is not provided.');
        }

        return new AzureBlobStorageAdapter(
            self::createContainerClient(),
            self::$containerName,
        );
    }

    private static function createContainerClient(): BlobContainerClient
    {
        $connectionString = self::getRequiredEnvironmentVariable('AZURE_STORAGE_CONNECTION_STRING');

        return BlobServiceClient::fromConnectionString($connectionString)->getContainerClient(self::$containerName);
    }

    public static function setUpBeforeClass(): void
    {
        self::$containerName = 'flysystem-'.bin2hex(random_bytes(8));
        self::createContainerClient()->create();
    }

    public static function tearDownAfterClass(): void
    {
        self::createContainerClient()->delete();
    }

    public function overwriting_a_file(): void
    {
        $this->runScenario(
            function () {
                $this->givenWeHaveAnExistingFile('path.txt');
                $adapter = $this->adapter();

                $adapter->write('path.txt', 'new contents', new Config);

                $contents = $adapter->read('path.txt');
                self::assertEquals('new contents', $contents);
            },
        );
    }

    public function setting_visibility(): void
    {
        self::markTestSkipped('Azure does not support visibility');
    }

    public function fetching_unknown_mime_type_of_a_file(): void
    {
        self::markTestSkipped('This adapter always returns a mime-type');
    }

    public function listing_contents_recursive(): void
    {
        self::markTestSkipped('This adapter does not support creating directories');
    }

    public function copying_a_file(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]),
            );

            $adapter->copy('source.txt', 'destination.txt', new Config);

            self::assertTrue($adapter->fileExists('source.txt'));
            self::assertTrue($adapter->fileExists('destination.txt'));
            self::assertEquals('contents to be copied', $adapter->read('destination.txt'));
        });
    }

    public function moving_a_file(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]),
            );
            $adapter->move('source.txt', 'destination.txt', new Config);
            self::assertFalse(
                $adapter->fileExists('source.txt'),
                'After moving a file should no longer exist in the original location.',
            );
            self::assertTrue(
                $adapter->fileExists('destination.txt'),
                'After moving, a file should be present at the new location.',
            );
            self::assertEquals('contents to be copied', $adapter->read('destination.txt'));
        });
    }

    public function copying_a_file_again(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config,
            );

            $adapter->copy('source.txt', 'destination.txt', new Config);

            self::assertTrue($adapter->fileExists('source.txt'));
            self::assertTrue($adapter->fileExists('destination.txt'));
            self::assertEquals('contents to be copied', $adapter->read('destination.txt'));
        });
    }

    public function checking_if_a_directory_exists_after_creating_it(): void
    {
        self::markTestSkipped('This adapter does not support creating directories');
    }

    public function setting_visibility_on_a_file_that_does_not_exist(): void
    {
        self::markTestSkipped('This adapter does not support visibility');
    }

    public function creating_a_directory(): void
    {
        self::markTestSkipped('This adapter does not support creating directories');
    }

    public function file_exists_on_directory_is_false(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();

            self::assertFalse($adapter->directoryExists('test'));

            $adapter->write('test/file.txt', '', new Config);

            self::assertTrue($adapter->directoryExists('test'));
            self::assertFalse($adapter->fileExists('test'));
        });
    }

    #[Test]
    public function it_can_set_cache_control_on_upload_via_http_headers_config(): void
    {
        $adapter = $this->adapter();

        $adapter->write('cache-control.txt', 'content', new Config([
            'httpHeaders' => [
                'cacheControl' => 'public, max-age=31536000',
            ],
        ]));

        $properties = self::createContainerClient()
            ->getBlobClient(self::$containerName.'/cache-control.txt')
            ->getProperties();

        self::assertSame('public, max-age=31536000', $properties->cacheControl);
    }

    #[Test]
    public function it_can_conditionally_overwrite_a_file_using_an_etag(): void
    {
        $adapter = $this->adapter();
        $adapter->write('conditional-etag.txt', 'original', new Config);

        $blob = self::createContainerClient()
            ->getBlobClient(self::$containerName.'/conditional-etag.txt');
        $eTag = $blob->getProperties()->eTag;
        self::assertNotNull($eTag);

        $adapter->write('conditional-etag.txt', 'updated', new Config([
            'conditions' => ['ifMatch' => (string) $eTag],
        ]));
        self::assertSame('updated', $adapter->read('conditional-etag.txt'));

        try {
            $adapter->write('conditional-etag.txt', 'stale update', new Config([
                'conditions' => ['ifMatch' => (string) $eTag],
            ]));
            self::fail('Expected a stale ETag write to fail.');
        } catch (UnableToWriteFile) {
            self::assertSame('updated', $adapter->read('conditional-etag.txt'));
        }
    }

    #[Test]
    public function it_can_require_a_file_not_to_exist(): void
    {
        $adapter = $this->adapter();
        $conditions = new Config([
            'conditions' => ['ifNoneMatch' => '*'],
        ]);

        $adapter->write('create-only.txt', 'original', $conditions);

        try {
            $adapter->write('create-only.txt', 'overwrite', $conditions);
            self::fail('Expected overwriting an existing file to fail.');
        } catch (UnableToWriteFile) {
            self::assertSame('original', $adapter->read('create-only.txt'));
        }
    }

    #[Test]
    public function it_can_write_to_a_leased_blob_using_the_lease_id(): void
    {
        $adapter = $this->adapter();
        $adapter->write('conditional-lease.txt', 'original', new Config);

        $blob = self::createContainerClient()
            ->getBlobClient(self::$containerName.'/conditional-lease.txt');
        $leaseClient = $blob->getBlobLeaseClient();
        $lease = $leaseClient->acquire(15);
        self::assertNotNull($lease->leaseId);

        try {
            $adapter->write('conditional-lease.txt', 'updated', new Config([
                'conditions' => ['leaseId' => $lease->leaseId],
            ]));

            self::assertSame('updated', $adapter->read('conditional-lease.txt'));
        } finally {
            $leaseClient->release();
        }
    }

    #[Test]
    public function it_rejects_an_invalid_write_conditions_option(): void
    {
        $adapter = $this->adapter();

        try {
            $adapter->write('invalid-conditions.txt', 'content', new Config([
                'conditions' => 'invalid',
            ]));
            self::fail('Expected invalid write conditions to fail.');
        } catch (UnableToWriteFile $exception) {
            $previous = $exception->getPrevious();
            self::assertInstanceOf(\RuntimeException::class, $previous);
            self::assertSame(
                'conditions must be an array.',
                $previous->getMessage(),
            );
        }
    }

    #[Test]
    public function setting_visibility_can_be_ignored_not_supported(): void
    {
        $this->givenWeHaveAnExistingFile('some-file.md');
        $this->expectNotToPerformAssertions();

        $adapter = new AzureBlobStorageAdapter(
            self::createContainerClient(),
            visibilityHandling: AzureBlobStorageAdapter::ON_VISIBILITY_IGNORE,
        );

        $adapter->setVisibility('some-file.md', 'public');
    }

    #[Test]
    public function setting_visibility_causes_errors(): void
    {
        $this->givenWeHaveAnExistingFile('some-file.md');
        $adapter = $this->adapter();

        $this->expectException(UnableToSetVisibility::class);

        $adapter->setVisibility('some-file.md', 'public');
    }

    #[Test]
    public function listing_contents_deep(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();

            $adapter->write('dir1/file1.txt', 'content1', new Config);
            $adapter->write('dir1/dir2/file2.txt', 'content2', new Config);
            $adapter->write('dir1/dir2/dir3/file3.txt', 'content3', new Config);
            $contents = iterator_to_array($adapter->listContents('', true));

            self::assertCount(6, $contents); // 3 files + 3 directories

            $paths = array_map(fn ($item) => $item->path(), $contents);
            self::assertContains('dir1', $paths);
            self::assertContains('dir1/file1.txt', $paths);
            self::assertContains('dir1/dir2', $paths);
            self::assertContains('dir1/dir2/file2.txt', $paths);
            self::assertContains('dir1/dir2/dir3', $paths);
            self::assertContains('dir1/dir2/dir3/file3.txt', $paths);
        });
    }

    #[Test]
    public function public_url_uses_direct_uri_when_enabled(): void
    {
        $this->givenWeHaveAnExistingFile('test-file.txt');

        $adapter = new AzureBlobStorageAdapter(
            self::createContainerClient(),
            self::$containerName,
            isPublicContainer: true,
        );

        $url = $adapter->publicUrl('test-file.txt', new Config);

        // Direct URL should not contain SAS token parameters
        self::assertStringNotContainsString('sig=', $url);
        self::assertStringNotContainsString('se=', $url);
        self::assertStringNotContainsString('sp=', $url);

        // But should contain the container and blob name
        self::assertStringContainsString(self::$containerName, $url);
        self::assertStringContainsString('test-file.txt', $url);
    }

    #[Test]
    public function public_url_uses_sas_token_by_default(): void
    {
        $this->givenWeHaveAnExistingFile('test-file.txt');

        $adapter = new AzureBlobStorageAdapter(
            self::createContainerClient(),
            self::$containerName,
        );

        $url = $adapter->publicUrl('test-file.txt', new Config);

        // URL with SAS token should contain these parameters
        self::assertStringContainsString('sig=', $url);
        self::assertStringContainsString('se=', $url);
        self::assertStringContainsString('sp=', $url);
    }

    #[Test]
    public function temporary_url_passes_response_headers_to_sas(): void
    {
        $this->givenWeHaveAnExistingFile('test-file.txt');

        $adapter = new AzureBlobStorageAdapter(
            self::createContainerClient(),
            self::$containerName,
        );

        $url = $adapter->temporaryUrl('test-file.txt', new \DateTimeImmutable('+5 minutes'), new Config([
            'httpHeaders' => [
                'cacheControl' => 'public, max-age=60',
                'contentDisposition' => 'attachment; filename="download.txt"',
                'contentEncoding' => 'identity',
                'contentLanguage' => 'en-US',
                'contentType' => 'text/plain',
            ],
        ]));

        $queryString = parse_url($url, PHP_URL_QUERY);
        self::assertIsString($queryString);

        $query = Query::parse($queryString);

        self::assertSame('public, max-age=60', $query['rscc'] ?? null);
        self::assertSame('attachment; filename="download.txt"', $query['rscd'] ?? null);
        self::assertSame('identity', $query['rsce'] ?? null);
        self::assertSame('en-US', $query['rscl'] ?? null);
        self::assertSame('text/plain', $query['rsct'] ?? null);
    }
}
