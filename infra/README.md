# Test Setup Guide

This guide explains how to set up the Azure-backed tests for this repository.

### 1. Create Azure Storage test resources

Deploy the templates in `infra/` to your Azure subscription. The deployment is subscription-scoped because [main.bicep](/Users/brecht.vermeersch/PhpstormProjects/oss/azure-storage-monorepo/infra/main.bicep:1) creates the resource group.

You will need an Azure identity with enough rights to:

- create a resource group
- deploy subscription-scope Bicep
- create storage accounts and file shares
- read storage account keys

In practice, `Contributor` on the target subscription is the simplest setup.

Example deployment:

```bash
az login
az account set --subscription "<subscription-id>"

az deployment sub create \
  --name "azure-oss-storage-tests" \
  --location eastus \
  --template-file infra/main.bicep \
  --parameters resourceGroupName="rg-azure-oss-storage-test"
```

After deployment, capture the outputs and set these environment variables for the storage-related tests:

- `AZURE_STORAGE_CONNECTION_STRING`
- `AZURE_STORAGE_CONNECTION_STRING_PUBLIC`
- `AZURE_STORAGE_CONNECTION_STRING_SOFT_DELETES`
- `AZURE_STORAGE_CONNECTION_STRING_VERSIONS`
- `AZURE_STORAGE_CONNECTION_STRING_SOFT_DELETES_VERSIONS`

### 2. Mount the Azure File Share

If you want to run the mounted Azure File Share tests, also set:

- `AZURE_STORAGE_FILE_SHARE_PATH`

Those tests expect a local mount path, not just the share name or connection string.

To mount the file share on Linux, first collect these values from the deployment outputs:

- `file_share_storage_account_name`
- `file_share_storage_account_key`
- `file_share_smb_path`

Then mount it:

```bash
sudo apt-get update
sudo apt-get install -y cifs-utils

sudo mkdir -p /etc/smbcredentials
cat <<EOF | sudo tee /etc/smbcredentials/<storage-account-name>.cred > /dev/null
username=<storage-account-name>
password=<storage-account-key>
EOF

sudo chmod 600 /etc/smbcredentials/<storage-account-name>.cred
sudo mkdir -p /mnt/azure-file-share

sudo mount -t cifs "//<storage-account-name>.file.core.windows.net/sdktests" /mnt/azure-file-share \
  -o "credentials=/etc/smbcredentials/<storage-account-name>.cred,serverino,nosharesock,actimeo=30,mfsymlinks,dir_mode=0777,file_mode=0777,vers=3.1.1"

export AZURE_STORAGE_FILE_SHARE_PATH=/mnt/azure-file-share
```

If you prefer, replace the explicit SMB path above with the `file_share_smb_path` deployment output.

### 3. Create a Microsoft Entra app for live Identity tests

Create a Microsoft Entra app registration and configure it with:

- a client secret
- certificate credentials
- Microsoft Graph application permission with admin consent

The certificate integration tests use certificate fixtures from [tests/Identity/fixtures](/Users/brecht.vermeersch/PhpstormProjects/oss/azure-storage-monorepo/tests/Identity/fixtures:1).

Upload these public certificate files to the app registration under `Certificates & secrets`:

- `client-cert-pem-unencrypted-public.crt`
- `client-cert-pem-encrypted-public.crt`
- `client-cert-pfx-no-password-public.crt`
- `client-cert-pfx-password-public.crt`
- `client-cert-pem-chain-leaf-public.crt`

The chain root and intermediate fixture certificates do not need to be uploaded for the current tests.

Then set these environment variables:

- `AZURE_TENANT_ID`
- `AZURE_CLIENT_ID`
- `AZURE_CLIENT_SECRET`

The live Identity tests request tokens for:

- `https://graph.microsoft.com/.default`

Make sure the app registration has an appropriate Microsoft Graph application permission and that admin consent has been granted in the tenant.

### 4. Run the tests

Once the environment variables are set, run:

```bash
./vendor/bin/phpunit
```
