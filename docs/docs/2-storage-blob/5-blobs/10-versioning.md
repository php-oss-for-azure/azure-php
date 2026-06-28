---
sidebar_position: 10
title: Blob versioning
---

Blob versioning automatically preserves earlier blob states after versioning is enabled for the storage account. Unlike [blob snapshots](./9-snapshots), versions are created by Azure when supported write operations create or modify a blob.

## List And Read Blob Versions

List versions and select one by its opaque version identifier:

```php
use AzureOss\Storage\Blob\Models\BlobInclude;
use AzureOss\Storage\Blob\Models\GetBlobsOptions;

$versions = $container->getBlobs(
    prefix: $blob->blobName,
    options: new GetBlobsOptions(includes: [BlobInclude::VERSIONS]),
);

foreach ($versions as $item) {
    if ($item->versionId === null || $item->isLatestVersion) {
        continue;
    }

    $version = $blob->withVersion($item->versionId);
    $content = $version->downloadStreaming()->content->getContents();
}
```

`getProperties()` and download results also expose `BlobProperties::$versionId` and `BlobProperties::$isLatestVersion`.

`withVersion()` returns a new client and does not modify the original client. Use `withVersion(null)` to derive a client without the version selector.

## Restore A Previous Version

Copy a previous version over the base blob to restore it. This creates a new current version and preserves the selected previous version:

```php
$version = $blob->withVersion($versionId);
$blob->syncCopyFromUri($version->uri);
```

## Delete A Previous Version

Delete one previous version without affecting the current blob:

```php
$blob->withVersion($versionId)->delete();
```

## Generate A Version SAS

Calling `generateSasUri()` on a version client creates a version-specific SAS (`sr=bv`) and preserves the version selector in the URI:

```php
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use AzureOss\Storage\Blob\Sas\BlobSasPermissions;

$versionSas = $version->generateSasUri(
    BlobSasBuilder::new()
        ->setPermissions(new BlobSasPermissions(read: true))
        ->setExpiresOn((new DateTimeImmutable)->modify('+5 minutes')),
);
```
