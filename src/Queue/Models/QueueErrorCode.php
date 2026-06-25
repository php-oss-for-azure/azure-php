<?php

declare(strict_types=1);

namespace AzureOss\Storage\Queue\Models;

enum QueueErrorCode: string
{
    case AccountAlreadyExists = 'AccountAlreadyExists';
    case AccountBeingCreated = 'AccountBeingCreated';
    case AccountIsDisabled = 'AccountIsDisabled';
    case AuthenticationFailed = 'AuthenticationFailed';
    case AuthorizationFailure = 'AuthorizationFailure';
    case AuthorizationPermissionMismatch = 'AuthorizationPermissionMismatch';
    case AuthorizationProtocolMismatch = 'AuthorizationProtocolMismatch';
    case AuthorizationResourceTypeMismatch = 'AuthorizationResourceTypeMismatch';
    case AuthorizationServiceMismatch = 'AuthorizationServiceMismatch';
    case AuthorizationSourceIPMismatch = 'AuthorizationSourceIPMismatch';
    case ConditionHeadersNotSupported = 'ConditionHeadersNotSupported';
    case ConditionNotMet = 'ConditionNotMet';
    case EmptyMetadataKey = 'EmptyMetadataKey';
    case FeatureVersionMismatch = 'FeatureVersionMismatch';
    case InsufficientAccountPermissions = 'InsufficientAccountPermissions';
    case InternalError = 'InternalError';
    case InvalidAuthenticationInfo = 'InvalidAuthenticationInfo';
    case InvalidHeaderValue = 'InvalidHeaderValue';
    case InvalidHttpVerb = 'InvalidHttpVerb';
    case InvalidInput = 'InvalidInput';
    case InvalidMarker = 'InvalidMarker';
    case InvalidMd5 = 'InvalidMd5';
    case InvalidMetadata = 'InvalidMetadata';
    case InvalidQueryParameterValue = 'InvalidQueryParameterValue';
    case InvalidRange = 'InvalidRange';
    case InvalidResourceName = 'InvalidResourceName';
    case InvalidUri = 'InvalidUri';
    case InvalidXmlDocument = 'InvalidXmlDocument';
    case InvalidXmlNodeValue = 'InvalidXmlNodeValue';
    case Md5Mismatch = 'Md5Mismatch';
    case MessageNotFound = 'MessageNotFound';
    case MessageTooLarge = 'MessageTooLarge';
    case MetadataTooLarge = 'MetadataTooLarge';
    case MissingContentLengthHeader = 'MissingContentLengthHeader';
    case MissingRequiredHeader = 'MissingRequiredHeader';
    case MissingRequiredQueryParameter = 'MissingRequiredQueryParameter';
    case MissingRequiredXmlNode = 'MissingRequiredXmlNode';
    case MultipleConditionHeadersNotSupported = 'MultipleConditionHeadersNotSupported';
    case OperationTimedOut = 'OperationTimedOut';
    case OutOfRangeInput = 'OutOfRangeInput';
    case OutOfRangeQueryParameterValue = 'OutOfRangeQueryParameterValue';
    case PopReceiptMismatch = 'PopReceiptMismatch';
    case QueueAlreadyExists = 'QueueAlreadyExists';
    case QueueBeingDeleted = 'QueueBeingDeleted';
    case QueueDisabled = 'QueueDisabled';
    case QueueNotEmpty = 'QueueNotEmpty';
    case QueueNotFound = 'QueueNotFound';
    case RequestBodyTooLarge = 'RequestBodyTooLarge';
    case RequestUrlFailedToParse = 'RequestUrlFailedToParse';
    case ResourceAlreadyExists = 'ResourceAlreadyExists';
    case ResourceNotFound = 'ResourceNotFound';
    case ResourceTypeMismatch = 'ResourceTypeMismatch';
    case ServerBusy = 'ServerBusy';
    case UnsupportedHeader = 'UnsupportedHeader';
    case UnsupportedHttpVerb = 'UnsupportedHttpVerb';
    case UnsupportedQueryParameter = 'UnsupportedQueryParameter';
    case UnsupportedXmlNode = 'UnsupportedXmlNode';
}
