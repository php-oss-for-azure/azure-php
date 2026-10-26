# Changelog

## Unreleased

### Fixed

- Fixed `AccessToken::fromTokenResponse()` computing `expires_in` expiries in wall-clock time, which shifted them by the DST offset when a transition fell within the token lifetime.

## 1.0.4

### Fixed

- Fixed the README logo so it renders on Packagist.

## 1.0.3

### Changed

- Added an internal `ManagedIdentityCredentialOptions::$imdsEndpoint` override to support deterministic managed identity testing and advanced hosting scenarios without changing the default IMDS behavior.
- Added Guzzle 8 development support while retaining Guzzle 7 support.
