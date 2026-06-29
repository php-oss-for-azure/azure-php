# Changelog

## Unreleased

### Changed

- Blob and container SAS generation now sign against a cloned `BlobSasBuilder`, preventing blob-specific state from leaking into later container SAS calls when the same builder instance is reused.
- `BlobSasBuilder::build()` now validates required fields and throws `UnableToGenerateSasException` instead of surfacing raw typed-property initialization errors.

## 2.2.0

### Added

- Added `BlobInclude` and support for requesting snapshots, metadata, uncommitted blobs, copy information, deleted blobs, tags, versions, and deleted blobs with versions from `BlobContainerClient::getBlobs()` and `getBlobsByHierarchy()`.
- Added list-response snapshot, metadata, tags, version, and deletion state to `Blob`.
- Added `BlobClient::undelete()` and `undeleteAsync()` for restoring soft-deleted blobs, snapshots, and versions.
- Added `DeleteSnapshotsOption` support for deleting a blob together with its snapshots or deleting only its snapshots.
- Added deletion time and remaining retention days to listed blob properties.
- Added deleted-container listing and restoration through `GetBlobContainersOptions`, `BlobContainerInclude`, and `BlobServiceClient::undeleteBlobContainer()`.
- Added container deletion state, deleted version, deletion time, remaining retention days, and requested metadata to container list results.
- Added blob snapshot creation through `BlobClient::createSnapshot()` and `createSnapshotAsync()`, configured with `CreateSnapshotOptions`.
- Added `BlobClient::withSnapshot()`, `BlobClient::withVersion()`, and matching block blob methods for targeting immutable snapshots and versions.
- Added snapshot- and version-specific blob SAS generation with the correct `bs` and `bv` signed resources.
- Added version identifiers to blob properties and copy results, plus current-version state to blob properties.

## 2.1.0

Changes since `2.0.1`.

### Added

- Added blob and container lease support through `BlobLeaseClient`, with synchronous and asynchronous acquire, renew, change, release, and break operations.
- Added conditional blob requests using ETag, date, and lease ID conditions. Conditions are supported by uploads, downloads, property and metadata operations, tag operations, deletes, block staging and commits, copies, and lease operations where Azure permits them.
- Added per-client Storage API version selection to blob service, container, blob, block blob, and lease client options. The selected version is propagated to clients created from a parent client.
- Added ETags to `BlobProperties` and `BlobContainerProperties`.
- Added `TagsTooLarge` to `BlobErrorCode`.

### Changed

- Blob and account SAS tokens now default to the latest generally available Storage API version.
- Container property requests now use the Azure `HEAD` operation.
