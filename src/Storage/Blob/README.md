# Azure Storage Blob PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/azure-oss/storage-blob.svg)](https://packagist.org/packages/azure-oss/storage-blob)
[![Packagist Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-blob)](https://packagist.org/packages/azure-oss/storage-blob)

Community-driven PHP SDKs for Azure, because Microsoft won't.

In November 2023, Microsoft officially archived their [Azure SDK for PHP](https://github.com/Azure/azure-sdk-for-php) and stopped maintaining PHP integrations for most Azure services. No migration path, no replacement — just a repository marked read-only.

We picked up where they left off.

<img src="https://azure-oss.github.io/img/logo.svg" width="150" alt="Screenshot">

## Package ecosystem

- **[azure-oss/storage](https://packagist.org/packages/azure-oss/storage)** — Meta package for the Storage SDKs
  - **[azure-oss/storage-common](https://packagist.org/packages/azure-oss/storage-common)** — Shared authentication, HTTP, and SAS primitives
  - **[azure-oss/storage-blob](https://packagist.org/packages/azure-oss/storage-blob)** — Blob Storage SDK
    - **[azure-oss/storage-blob-flysystem](https://packagist.org/packages/azure-oss/storage-blob-flysystem)** — Flysystem adapter
    - **[azure-oss/storage-blob-laravel](https://packagist.org/packages/azure-oss/storage-blob-laravel)** — Laravel filesystem driver
    - **[azure-oss/storage-blob-symfony](https://packagist.org/packages/azure-oss/storage-blob-symfony)** — Symfony Flysystem bridge
  - **[azure-oss/storage-queue](https://packagist.org/packages/azure-oss/storage-queue)** — Queue Storage SDK
    - **[azure-oss/storage-queue-laravel](https://packagist.org/packages/azure-oss/storage-queue-laravel)** — Laravel queue connector
  - **[azure-oss/storage-file-share](https://packagist.org/packages/azure-oss/storage-file-share)** — File Share SDK (under construction)
- **[azure-oss/identity](https://packagist.org/packages/azure-oss/identity)** — Microsoft Entra ID token authentication

## Features
- Authentication:
  - Connection strings (access keys)
  - Shared key credentials
  - Shared access signatures (SAS) for delegated, time-limited access
  - Microsoft Entra ID (token-based authentication) via azure-oss/azure-identity
- Local development:
  - Supports the Azurite emulator
- Containers:
  - Create, delete, and list (including filtering by prefix)
  - Configure public access when creating a container
  - Read properties and manage metadata
  - Acquire, renew, change, release, and break leases
  - List and restore soft-deleted containers
- Blobs:
  - Upload from strings or streams, with transfer tuning for large uploads
  - Set common HTTP headers (content type, cache control, etc.)
  - Download via streaming and access response properties
  - Protect reads and writes with ETag, date, and lease ID conditions
  - Acquire, renew, change, release, and break leases
  - Copy blobs (synchronous and asynchronous)
  - List blobs (flat, by prefix, and hierarchical listing) with page sizing
  - Delete and restore soft-deleted blobs
  - Create, read, copy, and delete snapshots
  - Select, read, restore, and delete blob versions
  - Read properties and manage metadata
  - Blob index tags: set/get tags and query blobs by tags (account or container scope)
- SAS:
  - Generate SAS for blobs, containers, and the account (when using credentials that can sign SAS)

## Documentation

You can read the documentation [here](https://azure-oss.github.io).

## Install

```shell
composer require azure-oss/storage-blob
```

## Quickstart

```php
<?php

use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;

$service = BlobServiceClient::fromConnectionString(
    getenv('AZURE_STORAGE_CONNECTION_STRING')
);

$container = $service->getContainerClient('quickstart');
$container->createIfNotExists();

$blob = $container->getBlobClient('hello.txt');

$blob->upload(
    'Hello from Azure-OSS',
    new UploadBlobOptions(contentType: 'text/plain')
);

$download = $blob->downloadStreaming();
$content = $download->content->getContents();

echo $content.PHP_EOL; // Hello from Azure-OSS

foreach ($container->getBlobs() as $item) {
    echo $item->name.PHP_EOL;
}

// Optional cleanup
$blob->deleteIfExists();
// $container->deleteIfExists();
```

## Conditional requests with ETags

Blob and container properties expose an `eTag`. Pass it through `BlobRequestConditions` to make a request succeed only while the resource is in the state you previously read. Azure rejects the request if another writer has changed the blob in the meantime.

```php
use AzureOss\Storage\Blob\Models\BlobRequestConditions;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Common\Models\ETag;

$properties = $blob->getProperties();

// Optimistic concurrency: overwrite only if the blob has not changed.
$blob->upload('updated content', new UploadBlobOptions(
    conditions: new BlobRequestConditions(ifMatch: $properties->eTag),
));

// Create only when no current blob exists.
$newBlob = $container->getBlobClient('new.txt');
$newBlob->upload('first content', new UploadBlobOptions(
    conditions: new BlobRequestConditions(ifNoneMatch: ETag::all()),
));
```

`BlobRequestConditions` also supports `ifModifiedSince`, `ifUnmodifiedSince`, and `leaseId`. Conditions are available on uploads, downloads, property and metadata operations, tag operations, deletes, block operations, copies, and lease operations where Azure supports them.

## Leases

Use a lease to obtain an exclusive write or delete lock on a blob. A lease may be infinite (the default) or finite; Azure accepts finite durations from 15 through 60 seconds. While the lease is active, protected operations must include its lease ID.

```php
use AzureOss\Storage\Blob\Models\BlobRequestConditions;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;

$leaseClient = $blob->getBlobLeaseClient();
$lease = $leaseClient->acquire(30);

try {
    $blob->upload('written under lease', new UploadBlobOptions(
        conditions: new BlobRequestConditions(leaseId: $lease->leaseId),
    ));

    $leaseClient->renew();
} finally {
    $leaseClient->release();
}
```

The lease client also provides `change()` and `break()`. Containers support the same lease client through `$container->getBlobLeaseClient()`; for a leased container, the lease ID is required when deleting the container.

## Snapshots

A snapshot is a read-only copy of a blob at a point in time. Create one, then use the returned snapshot identifier to build a client targeting it.

```php
use AzureOss\Storage\Blob\Models\CreateSnapshotOptions;
use AzureOss\Storage\Blob\Models\DeleteBlobOptions;
use AzureOss\Storage\Blob\Models\DeleteSnapshotsOption;

$snapshotInfo = $blob->createSnapshot(new CreateSnapshotOptions(
    metadata: ['purpose' => 'before-import'],
));

$snapshot = $blob->withSnapshot($snapshotInfo->snapshot);
$savedContent = $snapshot->downloadStreaming()->content->getContents();

// Restore the snapshot by copying it over the base blob.
$blob->syncCopyFromUri($snapshot->uri);

// Delete just this snapshot.
$snapshot->delete();

// Or delete the base blob together with every snapshot.
$blob->delete(new DeleteBlobOptions(
    snapshotsOption: DeleteSnapshotsOption::INCLUDE_SNAPSHOTS,
));
```

Use `DeleteSnapshotsOption::ONLY_SNAPSHOTS` to preserve the base blob while deleting all of its snapshots. To discover snapshots, list blobs with `BlobInclude::SNAPSHOTS`.

## Blob versioning

Blob versioning must first be enabled on the storage account. Azure then creates a version whenever a blob is created or modified. Version IDs are opaque strings; obtain them from blob properties or a listing and pass them to `withVersion()`.

```php
use AzureOss\Storage\Blob\Models\BlobInclude;
use AzureOss\Storage\Blob\Models\GetBlobsOptions;

$blob->upload('version one');
$versionId = $blob->getProperties()->versionId;

$blob->upload('version two');

$previousVersion = $blob->withVersion($versionId);
$oldContent = $previousVersion->downloadStreaming()->content->getContents();

// Promote the previous version by copying it over the current blob.
$blob->syncCopyFromUri($previousVersion->uri);

foreach ($container->getBlobs(options: new GetBlobsOptions(
    includes: [BlobInclude::VERSIONS],
)) as $item) {
    echo $item->name.' '.$item->versionId.' '.($item->isLatestVersion ? 'current' : 'previous').PHP_EOL;
}

// Delete one specific version without deleting the current blob.
$previousVersion->delete();
```

`withVersion(null)` returns a client targeting the base blob again. Snapshot and version clients preserve existing SAS query parameters.

## Soft delete and recovery

Blob or container soft delete must first be enabled on the storage account with an appropriate retention period. Deleted items can then be included in listings and recovered before that retention period expires.

```php
use AzureOss\Storage\Blob\Models\BlobContainerInclude;
use AzureOss\Storage\Blob\Models\BlobInclude;
use AzureOss\Storage\Blob\Models\GetBlobContainersOptions;
use AzureOss\Storage\Blob\Models\GetBlobsOptions;

$blob->delete();

foreach ($container->getBlobs(options: new GetBlobsOptions(
    includes: [BlobInclude::DELETED, BlobInclude::DELETED_WITH_VERSIONS],
)) as $item) {
    if ($item->name === $blob->blobName && $item->isDeleted) {
        echo 'Recoverable for '.$item->properties->remainingRetentionDays.' more day(s)'.PHP_EOL;
    }
}

// Restores a soft-deleted blob and its associated deleted snapshots or versions.
$blob->undelete();

// Soft-deleted containers are restored with their service-generated version.
foreach ($service->getBlobContainers(options: new GetBlobContainersOptions(
    includes: [BlobContainerInclude::DELETED],
)) as $deletedContainer) {
    if ($deletedContainer->isDeleted && $deletedContainer->versionId !== null) {
        $restored = $service->undeleteBlobContainer(
            $deletedContainer->name,
            $deletedContainer->versionId,
        );
    }
}
```

When blob versioning is enabled, `undelete()` restores deleted versions but does not automatically make an older version current. Promote the desired version with `syncCopyFromUri()` as shown above.

Network operations such as uploads, downloads, conditional requests, lease actions, snapshot creation, copies, deletes, and restores also have counterparts suffixed with `Async` that return Guzzle promises.

## License

This project is released under the MIT License. See [LICENSE](https://github.com/Azure-OSS/azure-php/blob/main/LICENSE) for details.
