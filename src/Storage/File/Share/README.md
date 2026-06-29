# Azure Storage File Share PHP SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/azure-oss/storage-file-share.svg)](https://packagist.org/packages/azure-oss/storage-file-share)
[![Packagist Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-file-share)](https://packagist.org/packages/azure-oss/storage-file-share)

A PHP SDK for Azure File Share management and service operations that are not exposed through standard SMB or NFS mounts.

## Install

```bash
composer require azure-oss/storage-file-share
```

## Scope

This package is not intended to replace an SMB or NFS mount.

If an operation is already handled well through a mounted Azure File Share, it is out of scope for this SDK. That includes routine filesystem-style work such as creating directories, reading files, writing files, renaming paths, and deleting content through the mounted share.

The purpose of this package is the opposite boundary: it should cover Azure Files capabilities that are not available through a normal mount, or are not practical to manage through one. That includes Azure-specific management, metadata, protocol-specific service features, and other control-plane or service-plane operations that need the Azure Files API rather than ordinary filesystem calls.

In short:

- Use **SMB** or **NFS** mounts for normal file and directory manipulation.
- Use `azure-oss/storage-file-share` for Azure Files features that a mount does not expose.

## SAS generation

This package includes a minimal client hierarchy and File Share service SAS builder for share and path-based SAS URIs.

SAS generation requires a `StorageSharedKeyCredential`. Clients created from a SAS-only connection string can use that SAS, but they cannot generate a new SAS.

`UseDevelopmentStorage=true` is not supported for this package. Azurite does not currently provide Azure Files endpoints, so File Share clients must target a real File service endpoint.

Azure Files service SAS distinguishes share (`sr=s`) and file (`sr=f`) resources. When you generate a SAS from a `ShareDirectoryClient`, the directory path is signed using the file-path form, matching the current Azure .NET SDK behavior.

```php
use AzureOss\Storage\File\Share\Sas\ShareFileSasPermissions;
use AzureOss\Storage\File\Share\Sas\ShareSasBuilder;
use AzureOss\Storage\File\Share\ShareServiceClient;

$service = ShareServiceClient::fromConnectionString($_ENV['AZURE_STORAGE_CONNECTION_STRING']);

$file = $service
    ->getShareClient('documents')
    ->getDirectoryClient('reports/2026')
    ->getFileClient('summary.txt');

$sasUri = $file->generateSasUri(
    ShareSasBuilder::new()
        ->setPermissions(new ShareFileSasPermissions(read: true))
        ->setExpiresOn(new DateTimeImmutable('+15 minutes')),
);
```

## Related packages

- **[azure-oss/storage](https://packagist.org/packages/azure-oss/storage)** — Meta package for the Storage SDKs
- **[azure-oss/storage-common](https://packagist.org/packages/azure-oss/storage-common)** — Shared authentication, HTTP, and SAS primitives
- **[azure-oss/storage-blob](https://packagist.org/packages/azure-oss/storage-blob)** — Blob Storage SDK
- **[azure-oss/storage-blob-flysystem](https://packagist.org/packages/azure-oss/storage-blob-flysystem)** — Flysystem adapter
- **[azure-oss/storage-blob-flysystem-bundle](https://packagist.org/packages/azure-oss/storage-blob-flysystem-bundle)** — Symfony Flysystem bundle
- **[azure-oss/storage-blob-laravel](https://packagist.org/packages/azure-oss/storage-blob-laravel)** — Laravel filesystem driver
- **[azure-oss/storage-queue](https://packagist.org/packages/azure-oss/storage-queue)** — Queue Storage SDK
- **[azure-oss/storage-queue-laravel](https://packagist.org/packages/azure-oss/storage-queue-laravel)** — Laravel queue connector
- **[azure-oss/identity](https://packagist.org/packages/azure-oss/identity)** — Microsoft Entra ID token authentication

## Maintenance

This package is part of the community-maintained `azure-oss` Azure SDKs for PHP. It is an independent project and is not affiliated with or endorsed by Microsoft.

## License

This project is released under the MIT License. See [LICENSE](https://github.com/Azure-OSS/azure-php/blob/main/LICENSE) for details.
