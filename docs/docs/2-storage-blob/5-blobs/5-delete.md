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

## Restore A Soft-Deleted Blob

When blob soft delete is enabled for the storage account, restore the blob during its retention period:

```php
$blob->undelete();
```

This restores the soft-deleted blob and all associated soft-deleted snapshots or versions. Calling `undelete()` for an active blob succeeds without changing it.

When blob versioning is enabled, deleting the current blob leaves its previous versions without selecting a current version. List versions with `BlobInclude::VERSIONS`, select the version to recover, and copy that version over the base blob. Calling `undelete()` restores versions that were themselves soft-deleted, but does not promote one to current.
