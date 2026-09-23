# Changelog

## Unreleased

### Added

- Added `BlobBatchClient` for deleting up to 256 blobs in a single Blob Batch request, created with `BlobContainerClient::getBlobBatchClient()` or `BlobServiceClient::getBlobBatchClient()`. A SAS needs Write (`w`) permission for the batch and Delete (`d`) permission for the blobs.
- Added `BlobBatchClient::deleteBlobs()` and `deleteBlobsAsync()`, accepting blob names or blob clients and an optional `DeleteSnapshotsOption` for every blob.
- Added `BlobBatch` for per-blob delete options, created with `BlobBatchClient::createBatch()` and sent with `submitBatch()` or `submitBatchAsync()` through any batch client with the same URI and credential.
- Added `BlobBatchException`, a `BlobStorageException` that carries a `BlobBatchFailure` with the position, the blob as given and the error of each failed sub-request. A batch the service rejects as a whole throws a plain `BlobStorageException`.

### Changed

- Requires `azure-oss/storage-common` `^2.3`.

## 2.3.1

### Fixed

- Fixed the README logo so it renders on Packagist.

## 2.3.0

### Changed

- Added support for Guzzle 8 while retaining Guzzle 7 support.
- Lease break requests now send their optional break period as an HTTP header string, as required by Guzzle 8.
- Async operations now declare their resolved promise result types for static analysis and IDEs.

## 2.2.2

### Changed

- Blob SAS generation now uses the shared storage-common date helper for SAS timestamp formatting.

## 2.2.1

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
