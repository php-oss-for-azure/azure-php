---
sidebar_position: 4
title: List blobs
---

## List All Blobs In A Container

```php
<?php

use AzureOss\Storage\Blob\BlobServiceClient;

$service = BlobServiceClient::fromConnectionString(getenv('AZURE_STORAGE_CONNECTION_STRING'));
$container = $service->getContainerClient('my-container');

foreach ($container->getBlobs() as $blob) {
    echo $blob->name.PHP_EOL;
}
```

## List Blobs With A Prefix

```php
foreach ($container->getBlobs('images/') as $blob) {
    echo $blob->name.PHP_EOL;
}
```

## List Blobs By Hierarchy

```php
use AzureOss\Storage\Blob\Models\Blob;
use AzureOss\Storage\Blob\Models\BlobPrefix;

foreach ($container->getBlobsByHierarchy('images/') as $item) {
    if ($item instanceof Blob) {
        echo "blob: {$item->name}".PHP_EOL;
    } elseif ($item instanceof BlobPrefix) {
        echo "prefix: {$item->name}".PHP_EOL;
    }
}
```

## Control Page Size

```php
use AzureOss\Storage\Blob\Models\GetBlobsOptions;

$options = new GetBlobsOptions(pageSize: 100);

foreach ($container->getBlobs(options: $options) as $blob) {
    // ...
}
```

## Include Additional Blob Data

Blob listings return a core set of properties by default. Request additional datasets with `BlobInclude` values:

```php
use AzureOss\Storage\Blob\Models\BlobInclude;
use AzureOss\Storage\Blob\Models\GetBlobsOptions;

$options = new GetBlobsOptions(includes: [
    BlobInclude::SNAPSHOTS,
    BlobInclude::METADATA,
    BlobInclude::TAGS,
    BlobInclude::VERSIONS,
]);

foreach ($container->getBlobs(options: $options) as $blob) {
    echo $blob->name.PHP_EOL;
    echo $blob->snapshot.PHP_EOL;
    print_r($blob->metadata ?? []);
    print_r($blob->tags ?? []);
}
```

Supported includes are `SNAPSHOTS`, `METADATA`, `UNCOMMITTED_BLOBS`, `COPY`, `DELETED`, `TAGS`, `VERSIONS`, and `DELETED_WITH_VERSIONS`. `Blob::$snapshot` is `null` unless snapshots are returned by Azure, `Blob::$metadata` is `null` unless metadata is returned, and `Blob::$tags` is `null` unless tags are returned. The same options can be passed to `getBlobsByHierarchy()`.

To discover recoverable blobs, request `BlobInclude::DELETED`. Deleted results have `Blob::$isDeleted` set to `true`; their deletion time and remaining retention window are available through `BlobProperties::$deletedOn` and `BlobProperties::$remainingRetentionDays`.

Use `BlobInclude::DELETED_WITH_VERSIONS` to list deleted base blobs that still have versions. These root entries have `Blob::$hasVersionsOnly` set to `true`.
