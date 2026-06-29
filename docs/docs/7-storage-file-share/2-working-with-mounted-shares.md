---
sidebar_position: 2
title: Mounted Share Boundary
---

This package does not aim to wrap the same workflows that Azure Files already supports through mounted shares.

## What stays out of scope

Azure Files already exposes standard network file-sharing protocols:

- **SMB** for broad compatibility across Windows, macOS, and Linux
- **NFS** for Linux environments that need NFS semantics and fit Azure Files NFS support constraints

When a share is mounted, the host can already perform routine filesystem work. Those workflows should stay with the mount rather than being reimplemented in this SDK.

Examples of out-of-scope operations:

- Creating folders and nested paths through the mounted filesystem
- Writing, reading, renaming, and deleting files through the mounted filesystem
- General-purpose application storage on a mounted share

## What belongs in scope

The SDK should instead cover Azure Files features that require direct service operations or Azure-specific semantics that mounts do not surface cleanly.

Examples include:

- Share management and configuration
- Share or file metadata and properties that need Azure Files APIs
- Snapshots
- Handle inspection and closure
- Range-oriented or protocol-specific service operations
- Azure authentication and request-level behavior

## Repository test strategy

The Azure integration workflow in this repository provisions a real Azure File Share, mounts it on the GitHub Actions runner over SMB, and runs a basic test that creates a directory and a file on that mounted path.

That test exists to validate the Azure test environment and mounted-share plumbing. It does not define the long-term SDK surface.
