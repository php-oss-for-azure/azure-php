---
sidebar_position: 9
title: Blob snapshots
---

Snapshots preserve a blob's state at a specific point in time. They are created explicitly by an application, unlike [blob versions](./10-versioning), which Azure creates automatically when versioning is enabled.

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

## Work With A Snapshot

Snapshots are immutable. A snapshot client can download content, read properties, act as a copy source, or delete that individual snapshot:

```php
$snapshot = $blob->withSnapshot($snapshotId);

$content = $snapshot->downloadStreaming()->content->getContents();
$snapshot->delete();
```

`withSnapshot()` returns a new client and does not modify the original client. Use `withSnapshot(null)` to derive a client without the snapshot selector.

## Generate A Snapshot SAS

Calling `generateSasUri()` on a snapshot client creates a snapshot-specific SAS (`sr=bs`) and preserves the snapshot selector in the URI:

```php
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use AzureOss\Storage\Blob\Sas\BlobSasPermissions;

$snapshotSas = $snapshot->generateSasUri(
    BlobSasBuilder::new()
        ->setPermissions(new BlobSasPermissions(read: true))
        ->setExpiresOn((new DateTimeImmutable)->modify('+5 minutes')),
);
```
