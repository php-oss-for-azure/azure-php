---
sidebar_position: 2
title: Generating SAS URLs
---

Use this package to generate Azure Files shared access signature (SAS) URLs.

## What this supports

The current package supports service SAS generation for Azure Files resources.

You can generate SAS URLs for:

- A share
- A file

SAS generation requires a shared key credential. A client created from a SAS-only connection string can use that SAS, but it cannot generate a new one.

## Example

```php
use AzureOss\Storage\File\Share\Sas\ShareFileSasPermissions;
use AzureOss\Storage\File\Share\Sas\ShareSasBuilder;
use AzureOss\Storage\File\Share\ShareServiceClient;

$service = ShareServiceClient::fromConnectionString($_ENV['AZURE_STORAGE_CONNECTION_STRING']);

$file = $service
    ->getShareClient('documents')
    ->getFileClient('reports/2026/summary.txt');

$sasUri = $file->generateSasUri(
    ShareSasBuilder::new()
        ->setPermissions(new ShareFileSasPermissions(read: true))
        ->setExpiresOn(new DateTimeImmutable('+15 minutes')),
);
```
