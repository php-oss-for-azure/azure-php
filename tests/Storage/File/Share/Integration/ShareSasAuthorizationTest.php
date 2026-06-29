<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\File\Share\Integration;

use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\File\Share\Sas\ShareFileSasPermissions;
use AzureOss\Storage\File\Share\Sas\ShareSasBuilder;
use AzureOss\Storage\File\Share\Sas\ShareSasPermissions;
use AzureOss\Storage\File\Share\ShareClient;
use AzureOss\Storage\File\Share\ShareFileClient;
use AzureOss\Tests\RequiresEnvironmentVariables;
use AzureOss\Tests\Storage\CreatesTempMountedShareDirectories;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShareSasAuthorizationTest extends TestCase
{
    use CreatesTempMountedShareDirectories, RequiresEnvironmentVariables;

    #[Test]
    public function file_sas_can_read_the_file_contents_from_azure_files(): void
    {
        $shareName = self::getRequiredEnvironmentVariable('AZURE_STORAGE_FILE_SHARE_NAME');
        $accountName = self::getRequiredEnvironmentVariable('AZURE_STORAGE_FILE_SHARE_ACCOUNT_NAME');
        $accountKey = self::getRequiredEnvironmentVariable('AZURE_STORAGE_FILE_SHARE_ACCOUNT_KEY');

        $directory = $this->tempMountedShareDirectory('sas-');
        $relativePath = $directory['relativePath'].'/hello.txt';
        $absoluteFile = $directory['absolutePath'].'/hello.txt';
        $contents = 'Azure Files SAS live test';

        file_put_contents($absoluteFile, $contents);

        $file = new ShareFileClient(
            new Uri("https://{$accountName}.file.core.windows.net/{$shareName}/{$relativePath}"),
            new StorageSharedKeyCredential($accountName, $accountKey),
        );
        $sasUri = $file->generateSasUri(
            ShareSasBuilder::new()
                ->setPermissions(new ShareFileSasPermissions(read: true))
                ->setVersion(ApiVersion::latestGA()->value)
                ->setExpiresOn(new \DateTimeImmutable('+10 minutes')),
        );

        $response = (new Client)->get($sasUri, [
            'headers' => [
                'x-ms-version' => ApiVersion::latestGA()->value,
            ],
        ]);

        self::assertSame($contents, (string) $response->getBody());
    }

    #[Test]
    public function share_sas_can_read_a_file_within_the_share_from_azure_files(): void
    {
        $shareName = self::getRequiredEnvironmentVariable('AZURE_STORAGE_FILE_SHARE_NAME');
        $accountName = self::getRequiredEnvironmentVariable('AZURE_STORAGE_FILE_SHARE_ACCOUNT_NAME');
        $accountKey = self::getRequiredEnvironmentVariable('AZURE_STORAGE_FILE_SHARE_ACCOUNT_KEY');

        $directory = $this->tempMountedShareDirectory('sas-share-');
        $relativePath = $directory['relativePath'].'/hello.txt';
        $absoluteFile = $directory['absolutePath'].'/hello.txt';
        $contents = 'Azure Files share SAS live test';

        file_put_contents($absoluteFile, $contents);

        $share = new ShareClient(
            new Uri("https://{$accountName}.file.core.windows.net/{$shareName}"),
            new StorageSharedKeyCredential($accountName, $accountKey),
        );
        $shareSasUri = $share->generateSasUri(
            ShareSasBuilder::new()
                ->setPermissions(new ShareSasPermissions(read: true))
                ->setVersion(ApiVersion::latestGA()->value)
                ->setExpiresOn(new \DateTimeImmutable('+10 minutes')),
        );
        $resourceUri = $shareSasUri
            ->withPath($shareSasUri->getPath().'/'.$relativePath)
            ->withQuery($shareSasUri->getQuery());

        $response = (new Client)->get($resourceUri, [
            'headers' => [
                'x-ms-version' => ApiVersion::latestGA()->value,
            ],
        ]);

        self::assertSame($contents, (string) $response->getBody());
    }
}
