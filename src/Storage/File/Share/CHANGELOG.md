# Changelog

## Unreleased

No user-facing changes since `0.1.1`.

## 0.1.1

### Changed

- File share SAS generation now uses the shared storage-common date helper for SAS timestamp formatting.

## 0.1.0

### Added

- Added `ShareServiceClient`, `ShareClient`, `ShareDirectoryClient`, and `ShareFileClient` for minimal Azure Files client navigation and SAS generation.
- Added `ShareSasBuilder` plus share and file SAS permission value objects, including path-based signing for directory clients.
- Added Azure Files SAS tests covering connection-string parsing, permission ordering, builder output, and live file/share SAS reads when Azure test infrastructure is available.
