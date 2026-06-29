@description('Azure region for all resources')
param location string

@description('Unique suffix to ensure globally unique storage account names')
param nameSuffix string = uniqueString(resourceGroup().id)

@description('Storage account SKU')
param skuName string = 'Standard_LRS'

// --------------------------------------------------------------------------
// Storage account configurations
// --------------------------------------------------------------------------
var storageConfigs = [
  {
    key: 'default'
    namePart: 'd'
    softDeleteEnabled: false
    versioningEnabled: false
    allowPublicAccess: false
  }
  {
    key: 'public'
    namePart: 'p'
    softDeleteEnabled: false
    versioningEnabled: false
    allowPublicAccess: true
  }
  {
    key: 'softdeletes'
    namePart: 's'
    softDeleteEnabled: true
    versioningEnabled: false
    allowPublicAccess: false
  }
  {
    key: 'versions'
    namePart: 'v'
    softDeleteEnabled: false
    versioningEnabled: true
    allowPublicAccess: false
  }
  {
    key: 'softdeletesversions'
    namePart: 'sv'
    softDeleteEnabled: true
    versioningEnabled: true
    allowPublicAccess: false
  }
]

var fileShareName = 'sdktests'

// --------------------------------------------------------------------------
// Storage Accounts
// --------------------------------------------------------------------------
resource storageAccounts 'Microsoft.Storage/storageAccounts@2023-01-01' = [
  for config in storageConfigs: {
    name: toLower('azureoss${config.namePart}${nameSuffix}')
    location: location
    kind: 'StorageV2'
    sku: {
      name: skuName
    }
    properties: {
      accessTier: 'Hot'
      supportsHttpsTrafficOnly: true
      minimumTlsVersion: 'TLS1_2'
      allowBlobPublicAccess: config.allowPublicAccess
      allowSharedKeyAccess: true
      networkAcls: {
        bypass: 'AzureServices'
        defaultAction: 'Allow'
      }
    }
  }
]

// --------------------------------------------------------------------------
// Blob Services — soft delete & versioning per account
// --------------------------------------------------------------------------
resource blobServices 'Microsoft.Storage/storageAccounts/blobServices@2023-01-01' = [
  for (config, i) in storageConfigs: {
    parent: storageAccounts[i]
    name: 'default'
    properties: {
      deleteRetentionPolicy: {
        enabled: config.softDeleteEnabled
        days: 1
      }
      containerDeleteRetentionPolicy: {
        enabled: config.softDeleteEnabled
        days: 1
      }
      isVersioningEnabled: config.versioningEnabled
    }
  }
]

// --------------------------------------------------------------------------
// File Services — basic SMB share for integration tests
// --------------------------------------------------------------------------
resource fileService 'Microsoft.Storage/storageAccounts/fileServices@2023-01-01' = {
  parent: storageAccounts[0]
  name: 'default'
}

resource testFileShare 'Microsoft.Storage/storageAccounts/fileServices/shares@2023-01-01' = {
  parent: fileService
  name: fileShareName
  properties: {
    accessTier: 'TransactionOptimized'
    enabledProtocols: 'SMB'
    shareQuota: 100
  }
}

// --------------------------------------------------------------------------
// Outputs — connection strings
// --------------------------------------------------------------------------
var suffix = environment().suffixes.storage

output connection_string string = 'DefaultEndpointsProtocol=https;AccountName=${storageAccounts[0].name};AccountKey=${storageAccounts[0].listKeys().keys[0].value};EndpointSuffix=${suffix}'

output connection_string_public string = 'DefaultEndpointsProtocol=https;AccountName=${storageAccounts[1].name};AccountKey=${storageAccounts[1].listKeys().keys[0].value};EndpointSuffix=${suffix}'

output connection_string_soft_deletes string = 'DefaultEndpointsProtocol=https;AccountName=${storageAccounts[2].name};AccountKey=${storageAccounts[2].listKeys().keys[0].value};EndpointSuffix=${suffix}'

output connection_string_versions string = 'DefaultEndpointsProtocol=https;AccountName=${storageAccounts[3].name};AccountKey=${storageAccounts[3].listKeys().keys[0].value};EndpointSuffix=${suffix}'

output connection_string_soft_deletes_versions string = 'DefaultEndpointsProtocol=https;AccountName=${storageAccounts[4].name};AccountKey=${storageAccounts[4].listKeys().keys[0].value};EndpointSuffix=${suffix}'

output file_share_name string = testFileShare.name

output file_share_storage_account_name string = storageAccounts[0].name

output file_share_storage_account_key string = storageAccounts[0].listKeys().keys[0].value

output file_share_smb_path string = '//${storageAccounts[0].name}.file.${suffix}/${testFileShare.name}'
