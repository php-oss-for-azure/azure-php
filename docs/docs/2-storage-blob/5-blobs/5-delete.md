---
sidebar_position: 5
title: Delete blobs
---

## Delete A Blob

```php
<?php

use AzureOss\Storage\Blob\BlobServiceClient;

$service = BlobServiceClient::fromConnectionString(getenv('AZURE_STORAGE_CONNECTION_STRING'));
$blob = $service->getContainerClient('my-container')->getBlobClient('hello.txt');

$blob->delete();
```

When snapshots exist, tell Azure whether to delete the base blob together with its snapshots or only the snapshots:

```php
use AzureOss\Storage\Blob\Models\DeleteBlobOptions;
use AzureOss\Storage\Blob\Models\DeleteSnapshotsOption;

$blob->delete(new DeleteBlobOptions(
    snapshotsOption: DeleteSnapshotsOption::INCLUDE_SNAPSHOTS,
));

// Preserve the base blob and delete all of its snapshots instead:
$blob->delete(new DeleteBlobOptions(
    snapshotsOption: DeleteSnapshotsOption::ONLY_SNAPSHOTS,
));
```

## Delete If Exists

```php
$blob->deleteIfExists();
```

Use `deleteIfExists()` when you want idempotent cleanup behavior without handling not-found exceptions.

## Delete Many Blobs In One Request

A batch client deletes up to 256 blobs with a single [Blob Batch](https://learn.microsoft.com/rest/api/storageservices/blob-batch) request. Get one from a container client to delete blobs by name, relative to the container:

```php
$container = $service->getContainerClient('my-container');

$container->getBlobBatchClient()->deleteBlobs([
    'hello.txt',
    'reports/2026-09.csv',
]);
```

Get one from the service client to delete blobs across containers. Names then start with the container name:

```php
$service->getBlobBatchClient()->deleteBlobs([
    'my-container/hello.txt',
    'archive/2025/report.csv',
]);
```

You can also pass blob clients, including clients that target a snapshot or version. To delete blobs that have snapshots, say what to do with the snapshots of every blob:

```php
use AzureOss\Storage\Blob\Models\DeleteSnapshotsOption;

$container->getBlobBatchClient()->deleteBlobs(['hello.txt', 'draft.txt'], DeleteSnapshotsOption::INCLUDE_SNAPSHOTS);
```

## Delete Blobs With Their Own Options

```php
use AzureOss\Storage\Blob\Models\BlobRequestConditions;
use AzureOss\Storage\Blob\Models\DeleteBlobOptions;
use AzureOss\Storage\Blob\Models\DeleteSnapshotsOption;

$batchClient = $container->getBlobBatchClient();

$batch = $batchClient->createBatch();
$batch->deleteBlob('hello.txt', new DeleteBlobOptions(snapshotsOption: DeleteSnapshotsOption::INCLUDE_SNAPSHOTS));
$batch->deleteBlob('reports/2026-09.csv', new DeleteBlobOptions(conditions: new BlobRequestConditions(leaseId: $leaseId)));
$batch->deleteBlob($container->getBlobClient('draft.txt'));

$batchClient->submitBatch($batch);
```

A batch can be submitted once, through any batch client with the same URI and credential. `deleteBlobsAsync()` and `submitBatchAsync()` send the request asynchronously.

## Handle Failed Deletes

A batch is not atomic, and the service applies its deletes in no guaranteed order. When some blobs cannot be deleted, the others are still deleted and a `BlobBatchException` reports each failed blob with its zero-based position in the batch, the blob as you passed it, and its error:

```php
use AzureOss\Storage\Blob\Exceptions\BlobBatchException;

try {
    $container->getBlobBatchClient()->deleteBlobs(['hello.txt', 'missing.txt']);
} catch (BlobBatchException $e) {
    foreach ($e->failures as $failure) {
        echo $failure->index.' '.$failure->blob.': '.$failure->exception->errorCode?->value.PHP_EOL; // 1 missing.txt: BlobNotFound
    }
}
```

`BlobBatchException` extends `BlobStorageException`. When the service rejects the whole batch, for example because a sub-request is malformed or the batch request is not authorized, a plain `BlobStorageException` is thrown and no blob is deleted.

The client retries a batch whose response was lost. Blobs the first attempt deleted are then reported as failed with `BlobNotFound`.

## Batch Authorization

Every delete in the batch is authorized with the credential or SAS of the client the batch client was created from. A SAS needs Write (`w`) permission for the batch request itself and Delete (`d`) permission for the blobs:

```php
use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\Sas\BlobContainerSasPermissions;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;

$sasUri = $container->generateSasUri(
    BlobSasBuilder::new()
        ->setPermissions(new BlobContainerSasPermissions(write: true, delete: true))
        ->setExpiresOn(new \DateTimeImmutable('+1 hour')),
);

(new BlobContainerClient($sasUri))->getBlobBatchClient()->deleteBlobs(['hello.txt']);
```

An account SAS needs the same permissions, with the `container` and `object` resource types.

## Restore A Soft-Deleted Blob

When blob soft delete is enabled for the storage account, restore the blob during its retention period:

```php
$blob->undelete();
```

This restores the soft-deleted blob and all associated soft-deleted snapshots or versions. Calling `undelete()` for an active blob succeeds without changing it.

When blob versioning is enabled, deleting the current blob leaves its previous versions without selecting a current version. List versions with `BlobInclude::VERSIONS`, select the version to recover, and copy that version over the base blob. Calling `undelete()` restores versions that were themselves soft-deleted, but does not promote one to current.
