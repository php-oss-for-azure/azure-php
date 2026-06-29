---
sidebar_position: 0
slug: /storage-file-share/core
title: Overview
---

`azure-oss/storage-file-share` is the Azure Files package for this PHP SDK monorepo.

## Current status

The package is being scaffolded and does not expose its Azure Files client surface yet.

## Scope boundary

This package is not meant to duplicate what Azure Files already provides through mounted shares.

If an operation is naturally performed through an **SMB** or **NFS** mount, it is out of scope for this SDK. Mounted shares are the right tool for ordinary filesystem-style work:

- Creating and deleting directories
- Reading and writing files
- Renaming paths
- Performing regular application I/O through a mounted filesystem

## What this SDK should cover

This SDK should focus on Azure Files capabilities that a mount does not expose, or does not expose in a way that maps cleanly to PHP application code.

That includes areas such as:

- Share-level management
- File and share properties and metadata
- Snapshots, handles, ranges, and other Azure Files service operations
- Authentication, request signing, and Azure-specific service behaviors
- Azure Files authentication and request plumbing shared with the rest of the Storage SDKs

## Practical guidance

Use a mounted Azure File Share for normal file manipulation.

Use `azure-oss/storage-file-share` for Azure Files features that require talking to the service as Azure Files, not just as a mounted filesystem.
