# Changelog

## Unreleased

### Added

- Added `BlobInclude` and support for requesting snapshots, metadata, uncommitted blobs, copy information, deleted blobs, tags, versions, and deleted blobs with versions from `BlobContainerClient::getBlobs()` and `getBlobsByHierarchy()`.
- Added list-response snapshot, metadata, tags, version, and deletion state to `Blob`.

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
