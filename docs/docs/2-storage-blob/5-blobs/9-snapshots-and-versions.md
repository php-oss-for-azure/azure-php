---
sidebar_position: 9
title: Snapshots and versions
---

Snapshots and versions both preserve an earlier blob state. Snapshots are created explicitly by an application. Versions are created automatically by Azure after blob versioning is enabled for the storage account.

## Create A Snapshot

Create a read-only snapshot of the current base blob:

```php
use AzureOss\Storage\Blob\Models\CreateSnapshotOptions;

$snapshotInfo = $blob->createSnapshot(new CreateSnapshotOptions(
    metadata: ['purpose' => 'before-migration'],
));

$snapshot = $blob->withSnapshot($snapshotInfo->snapshot);
$content = $snapshot->downloadStreaming()->content->getContents();
```

When `metadata` is empty, Azure copies the base blob's metadata to the snapshot. Conditions such as an expected ETag can be supplied through the options:

```php
use AzureOss\Storage\Blob\Models\BlobRequestConditions;
use AzureOss\Storage\Blob\Models\CreateSnapshotOptions;

$snapshotInfo = $blob->createSnapshot(new CreateSnapshotOptions(
    conditions: new BlobRequestConditions(ifMatch: $expectedETag),
));
```

`BlobSnapshotInfo` contains the opaque snapshot identifier, ETag, last-modified time, encryption state, and the version identifier returned when account versioning is enabled.

Snapshots are immutable. A snapshot client can download content, read properties, act as a copy source, or delete that individual snapshot:

```php
$snapshot->delete();
```

Calling `generateSasUri()` on a snapshot client creates a snapshot-specific SAS (`sr=bs`) and preserves the snapshot selector in the URI.

## Work With Blob Versions

Enable blob versioning on the storage account before relying on versions. Azure then creates a version when supported write operations create or modify a blob.

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

To restore a previous version, copy it over the base blob. This creates a new current version and preserves the selected previous version:

```php
$version = $blob->withVersion($versionId);
$blob->syncCopyFromUri($version->uri);
```

Delete one previous version without affecting the current blob:

```php
$blob->withVersion($versionId)->delete();
```

Calling `generateSasUri()` on a version client creates a version-specific SAS (`sr=bv`) and preserves the version selector in the URI.

Use `withSnapshot(null)` or `withVersion(null)` to remove that selector from a derived client. These methods return new clients and do not modify the original client.
