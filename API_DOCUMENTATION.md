# Installment Manager API Documentation

## Overview

This is a RESTful API for managing installment plans and customer payments. The API is built with Laravel 12 following SOLID principles and best practices.

## Architecture

### SOLID Principles Implementation

#### 1. Single Responsibility Principle (SRP)

-   **Controllers**: Handle HTTP requests/responses only
-   **Services**: Contain business logic
-   **Resources**: Transform data for API responses
-   **Traits**: Provide reusable response helpers

#### 2. Open/Closed Principle (OCP)

-   Services are open for extension through interfaces
-   New features can be added without modifying existing code

#### 3. Liskov Substitution Principle (LSP)

-   All services implement their respective interfaces
-   Services can be substituted without breaking functionality

#### 4. Interface Segregation Principle (ISP)

-   Separate interfaces for each service domain:
    -   `AuthServiceInterface`
    -   `UserServiceInterface`
    -   `CustomerServiceInterface`
    -   `InstallmentServiceInterface`

#### 5. Dependency Inversion Principle (DIP)

-   Controllers depend on interfaces, not concrete implementations
-   Dependency injection through `ServiceBindingProvider`

## Authentication

The API uses Laravel Sanctum for token-based authentication.

### Register

```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Registration successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "role": "user",
            "created_at": "2024-01-01T00:00:00.000000Z"
        },
        "token": "1|xxxxxxxxxxxxxxxxxxxxx",
        "token_type": "Bearer"
    }
}
```

### Login

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {...},
    "token": "2|xxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}
```

### Get Authenticated User

```http
GET /api/auth/me
Authorization: Bearer {token}
```

### Logout

```http
POST /api/auth/logout
Authorization: Bearer {token}
```

### Refresh Token

```http
POST /api/auth/refresh
Authorization: Bearer {token}
```

## Customers

### List Customers

```http
GET /api/customer-list
Authorization: Bearer {token}
```

**Response:**

```json
{
    "success": true,
    "message": "Customers retrieved successfully",
    "data": {
        "data": [
            {
                "id": 1,
                "name": "Jane Smith",
                "email": "jane@example.com",
                "phone": "+1234567890",
                "address": "123 Main St",
                "notes": "VIP Customer",
                "created_at": "2024-01-01T00:00:00.000000Z"
            }
        ],
        "current_page": 1,
        "per_page": 20,
        "total": 1
    }
}
```

### Create Customer

```http
POST /api/customer-create
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "phone": "+1234567890",
  "address": "123 Main St",
  "notes": "VIP Customer"
}
```

### Get Customer

```http
GET /api/customer-show/{id}
Authorization: Bearer {token}
```

### Update Customer

```http
PUT /api/customer-update/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Jane Smith Updated",
  "phone": "+9876543210"
}
```

### Delete Customer

```http
DELETE /api/customer-delete/{id}
Authorization: Bearer {token}
```

### Get Customer Statistics

```http
GET /api/customer-stats/{id}
Authorization: Bearer {token}
```

**Response:**

```json
{
    "success": true,
    "message": "Customer statistics retrieved successfully",
    "data": {
        "total_installments": 5,
        "active_installments": 2,
        "total_amount": 5000.0,
        "paid_amount": 2000.0
    }
}
```

## Installments

### List Installments

```http
GET /api/installment-list
Authorization: Bearer {token}
```

### Create Installment

```http
POST /api/installment-create
Authorization: Bearer {token}
Content-Type: application/json

{
  "customer_id": 1,
  "total_amount": 1200.00,
  "products": [
    {"name": "Laptop", "qty": 1, "price": 1000},
    {"name": "Mouse", "qty": 1, "price": 200}
  ],
  "start_date": "2024-01-01",
  "months": 12,
  "notes": "Monthly payment plan"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Installment created successfully",
  "data": {
    "id": 1,
    "customer_id": 1,
    "total_amount": 1200.00,
    "products": "Laptop, Mouse, Keyboard",
    "start_date": "2024-01-01",
    "end_date": "2024-12-01",
    "months": 12,
    "status": "active",
    "customer": {...},
    "items": [
      {
        "id": 1,
        "due_date": "2024-01-01",
        "amount": 100.00,
        "status": "pending"
      }
    ]
  }
}
```

### Get Installment

```http
GET /api/installment-show/{id}
Authorization: Bearer {token}
```

### Mark Installment Item as Paid

```http
POST /api/installment-item-pay/{item}
Authorization: Bearer {token}
Content-Type: application/json

{
  "paid_amount": 100.00,
  "reference": "PAYMENT-001"
}
```

### Get Overdue Items

```http
GET /api/installment-overdue
Authorization: Bearer {token}
```

### Get Due Soon Items

```http
GET /api/installment-due-soon
Authorization: Bearer {token}
```

## Dashboard

### Get Dashboard Analytics

```http
GET /api/dashboard
Authorization: Bearer {token}
```

**Response:**

```json
{
    "success": true,
    "message": "Dashboard analytics retrieved successfully",
    "data": {
        "dueSoon": 5,
        "overdue": 2,
        "outstanding": 3500.0,
        "collectedThisMonth": 1200.0,
        "totalInstallments": 25,
        "activeInstallments": 18,
        "completedInstallments": 7,
        "totalCustomers": 12,
        "activeCustomers": 10,
        "collectedLastMonth": 950.0,
        "collectionGrowth": 26.32,
        "upcoming": [
            {
                "installment_id": 1,
                "customer_name": "John Doe",
                "customer_email": "john@example.com",
                "customer_phone": "+1234567890",
                "total_amount": 1200.0,
                "months": 12,
                "due_date": "2024-01-15",
                "amount": 100.0,
                "status": "active",
                "item_status": "pending",
                "created_at": "2024-01-01T00:00:00.000000Z",
                "days_until_due": 3
            }
        ],
        "overduePayments": [
            {
                "installment_id": 2,
                "customer_name": "Jane Smith",
                "customer_email": "jane@example.com",
                "customer_phone": "+0987654321",
                "total_amount": 800.0,
                "months": 8,
                "due_date": "2024-01-05",
                "amount": 100.0,
                "status": "active",
                "item_status": "pending",
                "created_at": "2023-12-01T00:00:00.000000Z",
                "days_overdue": 5
            }
        ],
        "recentPayments": [
            {
                "installment_id": 3,
                "customer_name": "Bob Johnson",
                "customer_email": "bob@example.com",
                "due_date": "2024-01-10",
                "amount": 150.0,
                "paid_amount": 150.0,
                "paid_at": "2024-01-10T14:30:00.000000Z",
                "reference": "PAY-001",
                "days_since_paid": 2
            }
        ],
        "topCustomers": [
            {
                "id": 1,
                "name": "John Doe",
                "email": "john@example.com",
                "phone": "+1234567890",
                "active_installments": 3,
                "total_outstanding": 1200.0
            }
        ],
        "monthlyTrend": [
            {
                "month": "Aug 2023",
                "amount": 800.0,
                "year": 2023,
                "month_number": 8
            },
            {
                "month": "Sep 2023",
                "amount": 950.0,
                "year": 2023,
                "month_number": 9
            }
        ]
    }
}
```

## Users (Owner Only)

### List Users

```http
GET /api/user-list
Authorization: Bearer {token}
```

### Create User

```http
POST /api/user-create
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "New User",
  "email": "newuser@example.com",
  "password": "securepassword",
  "role": "user"
}
```

### Get User

```http
GET /api/user-show/{id}
Authorization: Bearer {token}
```

### Update User

```http
PUT /api/user-update/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Updated Name",
  "email": "updated@example.com"
}
```

### Delete User

```http
DELETE /api/user-delete/{id}
Authorization: Bearer {token}
```

## Error Responses

All errors follow a consistent format with standardized error codes:

```json
{
    "success": false,
    "message": "Error message",
    "error_code": "AUTH_001",
    "errors": {
        "field": ["Error details"]
    }
}
```

### HTTP Status Codes

-   `200` - Success
-   `201` - Created
-   `400` - Bad Request
-   `401` - Unauthorized
-   `403` - Forbidden
-   `404` - Not Found
-   `422` - Validation Error
-   `500` - Server Error

### Error Code Categories

The API uses a comprehensive error code system organized by functionality:

#### Authentication & Authorization (AUTH\_\*)

-   `AUTH_001` - Invalid Credentials
-   `AUTH_002` - Token Expired
-   `AUTH_003` - Token Invalid
-   `AUTH_004` - Access Denied
-   `AUTH_005` - Insufficient Permissions
-   `AUTH_006` - Account Disabled
-   `AUTH_007` - Email Not Verified

#### Validation Errors (VAL\_\*)

-   `VAL_001` - Validation Failed
-   `VAL_002` - Required Field Missing
-   `VAL_003` - Invalid Email Format
-   `VAL_004` - Password Too Weak
-   `VAL_005` - Invalid Date Format
-   `VAL_006` - Invalid Numeric Value
-   `VAL_007` - Value Out Of Range
-   `VAL_008` - Duplicate Entry

#### Customer Management (CUST\_\*)

-   `CUST_001` - Customer Not Found
-   `CUST_002` - Customer Already Exists
-   `CUST_003` - Customer Update Failed
-   `CUST_004` - Customer Delete Failed
-   `CUST_005` - Customer Access Denied

#### Installment Management (INST\_\*)

-   `INST_001` - Installment Not Found
-   `INST_002` - Installment Creation Failed
-   `INST_003` - Installment Update Failed
-   `INST_004` - Installment Delete Failed
-   `INST_005` - Installment Access Denied
-   `INST_006` - Invalid Installment Amount
-   `INST_007` - Invalid Installment Period
-   `INST_008` - Installment Already Completed
-   `INST_009` - Installment Item Not Found
-   `INST_010` - Installment Item Already Paid
-   `INST_011` - Payment Amount Mismatch
-   `INST_012` - Payment Date Invalid

#### User Management (USER\_\*)

-   `USER_001` - User Not Found
-   `USER_002` - User Already Exists
-   `USER_003` - User Creation Failed
-   `USER_004` - User Update Failed
-   `USER_005` - User Delete Failed
-   `USER_006` - User Access Denied
-   `USER_007` - Invalid User Role

#### Database Errors (DB\_\*)

-   `DB_001` - Database Connection Failed
-   `DB_002` - Database Query Failed
-   `DB_003` - Database Transaction Failed
-   `DB_004` - Database Constraint Violation

#### Business Logic (BIZ\_\*)

-   `BIZ_001` - Business Rule Violation
-   `BIZ_002` - Insufficient Funds
-   `BIZ_003` - Operation Not Allowed
-   `BIZ_004` - Resource In Use
-   `BIZ_005` - Invalid Operation

#### System Errors (SYS\_\*)

-   `SYS_001` - System Maintenance
-   `SYS_002` - Configuration Error
-   `SYS_003` - Service Unavailable

### Error Response Examples

#### Authentication Error

```json
{
    "success": false,
    "message": "Invalid credentials",
    "error_code": "AUTH_001",
    "status_code": 401
}
```

#### Validation Error

```json
{
    "success": false,
    "message": "Validation failed",
    "error_code": "VAL_001",
    "status_code": 422,
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password must be at least 8 characters."]
    }
}
```

#### Business Logic Error

```json
{
    "success": false,
    "message": "Installment item already paid",
    "error_code": "INST_010",
    "status_code": 400
}
```

## Rate Limiting

The API implements rate limiting to prevent abuse:

-   60 requests per minute for authenticated users
-   10 requests per minute for guest users

## Code Structure

```
app/
├── Contracts/
│   └── Services/              # Service interfaces (DIP)
│       ├── AuthServiceInterface.php
│       ├── CustomerServiceInterface.php
│       ├── InstallmentServiceInterface.php
│       └── UserServiceInterface.php
├── Http/
│   ├── Controllers/
│   │   └── Api/               # API Controllers (SRP)
│   │       ├── AuthController.php
│   │       ├── CustomerController.php
│   │       ├── InstallmentController.php
│   │       └── UserController.php
│   ├── Resources/             # API Resources (SRP)
│   │   ├── CustomerResource.php
│   │   ├── InstallmentResource.php
│   │   ├── InstallmentItemResource.php
│   │   └── UserResource.php
│   └── Traits/
│       └── ApiResponse.php    # Response helpers (SRP)
├── Services/                  # Business logic (SRP + ISP)
│   ├── AuthService.php
│   ├── CustomerService.php
│   ├── InstallmentService.php
│   └── UserService.php
└── Providers/
    └── ServiceBindingProvider.php  # Dependency injection (DIP)
```

## Testing

```bash
# Run tests
php artisan test

# Run specific test
php artisan test --filter=CustomerTest

# Run with coverage
php artisan test --coverage
```

## Best Practices

1. **Always use type hints** in method signatures
2. **Dependency injection** over service locator pattern
3. **API Resources** for consistent data transformation
4. **Form Requests** for validation
5. **Transactions** for data consistency
6. **Authorization** checks in services
7. **Consistent error handling** through exception handler

## Development Setup

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Run seeders (if any)
php artisan db:seed

# Start development server
php artisan serve
```

## Environment Variables

```env
APP_NAME="Installment Manager"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=installment_manager
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

## Frontend Integration Guides

### React Integration

For React developers, see [FRONTEND_INTEGRATION_REACT.md](./FRONTEND_INTEGRATION_REACT.md) for comprehensive examples including:

-   Complete API service setup with Axios
-   Authentication context and hooks
-   Dashboard components with tables and charts
-   Customer and installment management
-   Error handling and loading states
-   Ready-to-use components and screens

### Flutter Integration

For Flutter developers, see [FRONTEND_INTEGRATION_FLUTTER.md](./FRONTEND_INTEGRATION_FLUTTER.md) for mobile app development including:

-   Complete API service with Dio
-   State management with Riverpod
-   Dashboard screens with data tables
-   Customer and installment management
-   Error handling and loading states
-   Mobile-optimized UI components

## Support

For issues or questions, please contact the development team.
