# Changelog

## Unreleased

### Added

- Added an internal `RequestSigner`, implemented by the shared key and Microsoft Entra ID authorization middleware, for signing Blob Batch sub-requests. The HTTP client and the sub-requests share one signer, so a Microsoft Entra ID token is fetched once per batch.

## 2.2.1

### Fixed

- Fixed the README logo so it renders on Packagist.

## 2.2.0

### Changed

- Added support for Guzzle 8 and its compatible retry middleware while retaining Guzzle 7 support.
- HTTP failures without a response remain transport exceptions across both Guzzle major versions.
- Request headers, XML bodies, and promise rejections are normalized across the supported Guzzle versions.

## 2.1.1

### Changed

- Storage SAS generation now uses a shared storage-common date helper across common, blob, and file share packages, keeping the lowest Storage layer self-contained and leading the shared timestamp formatting behavior.

## 2.1.0

Changes since `2.0.0`.

### Added

- Added public `ApiVersion` cases for supported Storage service versions, plus `latestGA()` and `latestAzurite()` selectors.
- Added the `ETag` value object, including wildcard conditions through `ETag::all()` and value comparison through `equals()`.

### Changed

- Storage requests now default to API version `2026-06-06` against Azure and `2025-11-05` against Azurite. Callers can provide a specific `ApiVersion` when creating an HTTP client.
- Account SAS tokens now default to the latest generally available Storage API version.
