# Changelog

## Unreleased

No user-facing changes since `2.1.1`.

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
