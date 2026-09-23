---
sidebar_position: 0
slug: /storage-blob/core
title: Overview
---

azure-oss/storage is the core Azure Blob Storage SDK for PHP.

## Feature list

- Authentication:
  - Connection strings (access keys)
  - Shared key credentials
  - Shared access signatures (SAS) for delegated, time-limited access
  - Microsoft Entra ID (token-based authentication) via azure-oss/azure-identity
- Local development:
  - Supports the Azurite emulator
- Containers:
  - Create, delete, and list (including filtering by prefix)
  - Configure public access when creating a container
  - Read properties and manage metadata
- Blobs:
  - Upload from strings or streams, with transfer tuning for large uploads
  - Set common HTTP headers (content type, cache control, etc.)
  - Download via streaming and access response properties
  - Copy blobs (synchronous and asynchronous)
  - List blobs (flat, by prefix, and hierarchical listing) with page sizing
  - Delete blobs, including up to 256 blobs in a single batch request
  - Read properties and manage metadata
  - Blob index tags: set/get tags and query blobs by tags (account or container scope)
- SAS:
  - Generate SAS for blobs, containers, and the account (when using credentials that can sign SAS)

## Notes

- Token-based authentication cannot generate SAS URLs in this SDK. Use shared key credentials for SAS-based workflows.
