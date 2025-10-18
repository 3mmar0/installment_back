<?php

namespace App\Enums;

enum ErrorCodes: string
{
    // HTTP Status Codes
    case BadRequest = '400';
    case Unauthorized = '401';
    case Forbidden = '403';
    case NotFound = '404';
    case MethodNotAllowed = '405';
    case Conflict = '409';
    case UnprocessableEntity = '422';
    case TooManyRequests = '429';
    case InternalServerError = '500';
    case ServiceUnavailable = '503';

        // Authentication & Authorization Errors
    case InvalidCredentials = 'AUTH_001';
    case TokenExpired = 'AUTH_002';
    case TokenInvalid = 'AUTH_003';
    case AccessDenied = 'AUTH_004';
    case InsufficientPermissions = 'AUTH_005';
    case AccountDisabled = 'AUTH_006';
    case EmailNotVerified = 'AUTH_007';

        // Validation Errors
    case ValidationFailed = 'VAL_001';
    case RequiredFieldMissing = 'VAL_002';
    case InvalidEmailFormat = 'VAL_003';
    case PasswordTooWeak = 'VAL_004';
    case InvalidDateFormat = 'VAL_005';
    case InvalidNumericValue = 'VAL_006';
    case ValueOutOfRange = 'VAL_007';
    case DuplicateEntry = 'VAL_008';

        // Customer Management Errors
    case CustomerNotFound = 'CUST_001';
    case CustomerAlreadyExists = 'CUST_002';
    case CustomerUpdateFailed = 'CUST_003';
    case CustomerDeleteFailed = 'CUST_004';
    case CustomerAccessDenied = 'CUST_005';

        // Installment Management Errors
    case InstallmentNotFound = 'INST_001';
    case InstallmentCreationFailed = 'INST_002';
    case InstallmentUpdateFailed = 'INST_003';
    case InstallmentDeleteFailed = 'INST_004';
    case InstallmentAccessDenied = 'INST_005';
    case InvalidInstallmentAmount = 'INST_006';
    case InvalidInstallmentPeriod = 'INST_007';
    case InstallmentAlreadyCompleted = 'INST_008';
    case InstallmentItemNotFound = 'INST_009';
    case InstallmentItemAlreadyPaid = 'INST_010';
    case PaymentAmountMismatch = 'INST_011';
    case PaymentDateInvalid = 'INST_012';

        // User Management Errors
    case UserNotFound = 'USER_001';
    case UserAlreadyExists = 'USER_002';
    case UserCreationFailed = 'USER_003';
    case UserUpdateFailed = 'USER_004';
    case UserDeleteFailed = 'USER_005';
    case UserAccessDenied = 'USER_006';
    case InvalidUserRole = 'USER_007';

        // Database Errors
    case DatabaseConnectionFailed = 'DB_001';
    case DatabaseQueryFailed = 'DB_002';
    case DatabaseTransactionFailed = 'DB_003';
    case DatabaseConstraintViolation = 'DB_004';

        // Business Logic Errors
    case BusinessRuleViolation = 'BIZ_001';
    case InsufficientFunds = 'BIZ_002';
    case OperationNotAllowed = 'BIZ_003';
    case ResourceInUse = 'BIZ_004';
    case InvalidOperation = 'BIZ_005';

        // External Service Errors
    case ExternalServiceUnavailable = 'EXT_001';
    case ExternalServiceTimeout = 'EXT_002';
    case ExternalServiceError = 'EXT_003';

        // Rate Limiting Errors
    case RateLimitExceeded = 'RATE_001';
    case TooManyAttempts = 'RATE_002';

        // File Upload Errors
    case FileUploadFailed = 'FILE_001';
    case InvalidFileType = 'FILE_002';
    case FileTooLarge = 'FILE_003';
    case FileNotFound = 'FILE_004';

        // System Errors
    case SystemMaintenance = 'SYS_001';
    case ConfigurationError = 'SYS_002';
}
