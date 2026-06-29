---
sidebar_position: 0
slug: /storage-file-share/core
title: Overview
---

`azure-oss/storage-file-share` is the Azure Files package for this PHP SDK monorepo.

## What this package does

This package provides Azure Files API access for PHP when your application needs Azure-specific service behavior rather than ordinary filesystem operations.

Today, its public scope is centered on Azure Files shared access signatures (SAS) and related service access patterns.

## What this package does not do

This package does not replace an Azure Files mount.

If an operation is naturally performed through an **SMB** or **NFS** mount, it is out of scope for this SDK. Mounted shares are the right tool for ordinary filesystem-style work:

- Creating and deleting directories
- Reading and writing files
- Renaming paths
- Performing regular application I/O through a mounted filesystem

## Practical guidance

Use a mounted Azure File Share for normal file manipulation.

For mount instructions, use the Microsoft Learn documentation for [SMB on Windows](https://learn.microsoft.com/en-us/azure/storage/files/storage-how-to-use-files-windows), [SMB on Linux](https://learn.microsoft.com/en-us/azure/storage/files/storage-how-to-use-files-linux), [SMB on macOS](https://learn.microsoft.com/en-us/azure/storage/files/storage-how-to-use-files-mac), or [NFS on Linux](https://learn.microsoft.com/en-us/azure/storage/files/storage-files-how-to-mount-nfs-shares).

Use `azure-oss/storage-file-share` when you need Azure Files service access that is not just mounted-share file I/O.
