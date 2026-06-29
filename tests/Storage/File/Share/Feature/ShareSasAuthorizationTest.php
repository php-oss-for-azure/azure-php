<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\File\Share\Feature;

use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\File\Share\Sas\ShareFileSasPermissions;
use AzureOss\Storage\File\Share\Sas\ShareSasBuilder;
use AzureOss\Storage\File\Share\Sas\ShareSasPermissions;
use AzureOss\Storage\File\Share\ShareClient;
use AzureOss\Storage\File\Share\ShareFileClient;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShareSasAuthorizationTest extends TestCase
{
    #[Test]
    public function file_sas_can_read_the_file_contents_from_azure_files(): void
    {
        $mountPath = getenv('AZURE_STORAGE_FILE_SHARE_PATH');
        $shareName = getenv('AZURE_STORAGE_FILE_SHARE_NAME');
        $accountName = getenv('AZURE_STORAGE_FILE_SHARE_ACCOUNT_NAME');
        $accountKey = getenv('AZURE_STORAGE_FILE_SHARE_ACCOUNT_KEY');

        if (
            $mountPath === false || $mountPath === ''
            || $shareName === false || $shareName === ''
            || $accountName === false || $accountName === ''
            || $accountKey === false || $accountKey === ''
        ) {
            self::markTestSkipped('Azure Files live-test environment variables are not configured.');
        }

        $relativePath = 'sas-'.bin2hex(random_bytes(8)).'/hello.txt';
        $directory = dirname($relativePath);
        $absoluteDirectory = rtrim($mountPath, '/').'/'.$directory;
        $absoluteFile = rtrim($mountPath, '/').'/'.$relativePath;
        $contents = 'Azure Files SAS live test';

        mkdir($absoluteDirectory, 0777, true);
        file_put_contents($absoluteFile, $contents);

        try {
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
        } finally {
            @unlink($absoluteFile);
            @rmdir($absoluteDirectory);
        }
    }

    #[Test]
    public function share_sas_can_read_a_file_within_the_share_from_azure_files(): void
    {
        $mountPath = getenv('AZURE_STORAGE_FILE_SHARE_PATH');
        $shareName = getenv('AZURE_STORAGE_FILE_SHARE_NAME');
        $accountName = getenv('AZURE_STORAGE_FILE_SHARE_ACCOUNT_NAME');
        $accountKey = getenv('AZURE_STORAGE_FILE_SHARE_ACCOUNT_KEY');

        if (
            $mountPath === false || $mountPath === ''
            || $shareName === false || $shareName === ''
            || $accountName === false || $accountName === ''
            || $accountKey === false || $accountKey === ''
        ) {
            self::markTestSkipped('Azure Files live-test environment variables are not configured.');
        }

        $relativePath = 'sas-share-'.bin2hex(random_bytes(8)).'/hello.txt';
        $directory = dirname($relativePath);
        $absoluteDirectory = rtrim($mountPath, '/').'/'.$directory;
        $absoluteFile = rtrim($mountPath, '/').'/'.$relativePath;
        $contents = 'Azure Files share SAS live test';

        mkdir($absoluteDirectory, 0777, true);
        file_put_contents($absoluteFile, $contents);

        try {
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
        } finally {
            @unlink($absoluteFile);
            @rmdir($absoluteDirectory);
        }
    }
}
