# Changelog

## Unreleased

### Added

- Added `ShareServiceClient`, `ShareClient`, `ShareDirectoryClient`, and `ShareFileClient` for minimal Azure Files client navigation and SAS generation.
- Added `ShareSasBuilder` plus share and file SAS permission value objects, including path-based signing for directory clients.
- Added Azure Files SAS tests covering connection-string parsing, permission ordering, builder output, and live file/share SAS reads when Azure test infrastructure is available.
