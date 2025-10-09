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
GET /api/customers
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
POST /api/customers
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
GET /api/customers/{id}
Authorization: Bearer {token}
```

### Update Customer

```http
PUT /api/customers/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Jane Smith Updated",
  "phone": "+9876543210"
}
```

### Delete Customer

```http
DELETE /api/customers/{id}
Authorization: Bearer {token}
```

### Get Customer Statistics

```http
GET /api/customers/{id}/stats
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
GET /api/installments
Authorization: Bearer {token}
```

### Create Installment

```http
POST /api/installments
Authorization: Bearer {token}
Content-Type: application/json

{
  "customer_id": 1,
  "total_amount": 1200.00,
  "products": "Laptop, Mouse, Keyboard",
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
GET /api/installments/{id}
Authorization: Bearer {token}
```

### Mark Installment Item as Paid

```http
POST /api/installment-items/{item_id}/pay
Authorization: Bearer {token}
Content-Type: application/json

{
  "paid_amount": 100.00,
  "reference": "PAYMENT-001"
}
```

### Get Overdue Items

```http
GET /api/installments/overdue
Authorization: Bearer {token}
```

### Get Due Soon Items

```http
GET /api/installments/due-soon
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
    "outstanding": 3500.00,
    "collectedThisMonth": 1200.00,
    "upcoming": [...]
  }
}
```

## Users (Owner Only)

### List Users

```http
GET /api/users
Authorization: Bearer {token}
```

### Create User

```http
POST /api/users
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
GET /api/users/{id}
Authorization: Bearer {token}
```

### Update User

```http
PUT /api/users/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Updated Name",
  "email": "updated@example.com"
}
```

### Delete User

```http
DELETE /api/users/{id}
Authorization: Bearer {token}
```

## Error Responses

All errors follow a consistent format:

```json
{
    "success": false,
    "message": "Error message",
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

## Support

For issues or questions, please contact the development team.
