targetScope = 'subscription'

@description('Name of the resource group to create')
param resourceGroupName string = 'rg-azure-oss-storage-test'

@description('Azure region for all resources')
param location string = 'eastus'

// --------------------------------------------------------------------------
// Create the Resource Group
// --------------------------------------------------------------------------
resource rg 'Microsoft.Resources/resourceGroups@2024-03-01' = {
  name: resourceGroupName
  location: location
}

// --------------------------------------------------------------------------
// Deploy storage accounts into the new resource group via module
// --------------------------------------------------------------------------
module storageModule 'storage.bicep' = {
  name: 'azureOssStorageDeployment'
  scope: rg
  params: {
    location: location
  }
}

// --------------------------------------------------------------------------
// Surface connection strings from the module
// --------------------------------------------------------------------------
output connection_string string = storageModule.outputs.connection_string
output connection_string_public string = storageModule.outputs.connection_string_public
output connection_string_soft_deletes string = storageModule.outputs.connection_string_soft_deletes
output connection_string_versions string = storageModule.outputs.connection_string_versions
output connection_string_soft_deletes_versions string = storageModule.outputs.connection_string_soft_deletes_versions
output file_share_name string = storageModule.outputs.file_share_name
output file_share_storage_account_name string = storageModule.outputs.file_share_storage_account_name
output file_share_storage_account_key string = storageModule.outputs.file_share_storage_account_key
output file_share_smb_path string = storageModule.outputs.file_share_smb_path
