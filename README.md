# 🚀 Installment Manager API

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-red?style=for-the-badge&logo=laravel" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.2+-blue?style=for-the-badge&logo=php" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/SOLID-Principles-green?style=for-the-badge" alt="SOLID">
  <img src="https://img.shields.io/badge/REST-API-orange?style=for-the-badge" alt="REST API">
</p>

<p align="center">
  A professional RESTful API for managing installment plans and customer payments, built with Laravel 12 following SOLID principles and enterprise-level best practices.
</p>

---

## 📋 Table of Contents

-   [Overview](#-overview)
-   [Features](#-features)
-   [Architecture](#-architecture)
-   [Quick Start](#-quick-start)
-   [API Documentation](#-api-documentation)
-   [Tech Stack](#-tech-stack)
-   [Project Structure](#-project-structure)
-   [Development](#-development)
-   [Testing](#-testing)
-   [Documentation](#-documentation)
-   [License](#-license)

---

## 🎯 Overview

The **Installment Manager API** is a comprehensive solution for businesses that need to manage customer installment plans and payment tracking. Built with modern PHP practices and SOLID principles, it provides a robust, scalable, and maintainable API for:

-   Customer management
-   Installment plan creation and tracking
-   Automatic payment scheduling
-   Payment collection tracking
-   Analytics and dashboard metrics
-   User and role management

---

## ✨ Features

### 🔐 Authentication & Authorization

-   Token-based authentication using Laravel Sanctum
-   Role-based access control (Owner/User)
-   Secure API endpoints
-   Token refresh capability

### 👥 Customer Management

-   Full CRUD operations
-   Customer statistics and analytics
-   Pagination support
-   Search and filtering

### 💰 Installment Management

-   Create installment plans with automatic payment scheduling
-   Track payment status (pending/paid)
-   Mark payments as paid with reference tracking
-   Calculate payment amounts automatically
-   View overdue and due-soon items
-   Payment history tracking

### 📊 Dashboard & Analytics

-   Real-time metrics
-   Overdue payment count
-   Due soon alerts (7-day window)
-   Outstanding amount tracking
-   Monthly collection reports
-   Upcoming payment list

### 👤 User Management (Owner Only)

-   Create and manage users
-   Assign roles
-   View user activity
-   Secure user operations

### 📦 Subscription Plans & Usage Limits (محدّث)

-   بنية جديدة للاشتراكات تعتمد على الجداول:
    -   `subscriptions`: تعريف الخطط (السعر، المدة، الحدود، الميزات).
    -   `subscription_assignments`: تعيين الخطة للمستخدم مع الحالة والتواريخ.
    -   `user_limits`: المصدر الرئيس للحدود الحالية والاستخدام لكل مستخدم.
-   `LimitsHelper` يتولى إنشاء/تحديث حدود المستخدم، احتساب الاستهلاك، وفحص الصلاحيات.
-   جميع رسائل الـ API أصبحت بالعربية (نجاح/أخطاء/تنبيهات تجاوز الحدود).
-   كل رد للواجهات الأمامية يعود بالصورة الموحدة:

    ```json
    {
      "success": true,
      "message": "تمت العملية بنجاح",
      "data": { ... }
    }
    ```

-   أهم النقاط التي يجب أن تستهلكها الواجهة الأمامية:
    -   `POST /api/auth/login`, `POST /api/auth/register`, `GET /api/auth/me`\
        تعيد بيانات المستخدم متضمنة `current_subscription` و`user_limit`.
    -   `GET /api/subscriptions`\
        قائمة الخطط الفعّالة (مفتوحة للجميع).
    -   (للمالك) `GET /api/subscriptions/admin`, `POST /api/subscriptions`, `PUT /api/subscriptions/{id}`, `DELETE /api/subscriptions/{id}`, `POST /api/subscriptions/{id}/assign`\
        إدارة الخطط وتعيينها للمستخدمين، ويتم إرجاع `UserLimitResource`.
    -   مسارات حدود المستخدم:
        -   `GET /api/limits/current`
        -   `GET /api/limits/can-create/{resource}`
        -   `POST /api/limits/increment/{resource}`, `POST /api/limits/decrement/{resource}`
        -   `GET /api/limits/feature/{feature}`
        -   (للمالك) `GET/POST/PUT/DELETE /api/limits`
-   الاستجابات الآن تتضمن تفاصيل الاشتراك الحالي (الاسم، الحالة، السعر، المدة، التواريخ، الحدود المتبقية) لتسهيل عرض المعلومات في الواجهة الأمامية.

---

## 🏗️ Architecture

This project is built following **SOLID principles** for maximum maintainability and scalability:

### Single Responsibility Principle (SRP)

-   **Controllers**: Handle only HTTP requests/responses
-   **Services**: Contain only business logic
-   **Resources**: Transform data only
-   **Traits**: Provide reusable helpers

### Open/Closed Principle (OCP)

-   Services are open for extension through interfaces
-   New features can be added without modifying existing code

### Liskov Substitution Principle (LSP)

-   All services implement their respective interfaces
-   Services can be swapped without breaking functionality

### Interface Segregation Principle (ISP)

-   Focused, specific interfaces for each service domain
-   No client is forced to depend on methods it doesn't use

### Dependency Inversion Principle (DIP)

-   Controllers depend on interfaces, not concrete implementations
-   Dependency injection configured via `ServiceBindingProvider`

---

## 🚀 Quick Start

### Prerequisites

-   PHP 8.2 or higher
-   Composer
-   MySQL 8.0 or higher
-   Git

### Installation

1. **Clone the repository**

    ```bash
    git clone <repository-url>
    cd installment_back
    ```

2. **Install dependencies**

    ```bash
    composer install
    ```

3. **Setup environment**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

4. **Configure database**

    Edit `.env` file:

    ```env
    DB_DATABASE=installment_manager
    DB_USERNAME=your_username
    DB_PASSWORD=your_password
    ```

5. **Run migrations**

    ```bash
    php artisan migrate
    ```

6. **Start development server**

    ```bash
    php artisan serve
    ```

7. **View documentation**

    Open your browser and navigate to:

    ```
    http://localhost:8000/documentation        # Overview
    http://localhost:8000/documentation/api   # Full API Documentation
    ```

### Test the API

```bash
# Register a new user
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'

# Login and get token
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'

# Use the token for authenticated requests
curl -X GET http://localhost:8000/api/dashboard \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 📚 API Documentation

### Base URL

```
http://localhost:8000/api
```

### Authentication

All protected endpoints require the `Authorization` header:

```
Authorization: Bearer {your-token}
```

### Response Format

All API responses follow this structure:

```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": { ... }
}
```

All messages are in Arabic (العربية).

---

## 🔐 Authentication Endpoints

### `POST /api/auth/register` (Public)

Register a new user.

**Request Body:**

```json
{
    "name": "أحمد محمد",
    "email": "ahmed@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "subscription_id": 1 // Optional
}
```

**Response (200 OK):**

```json
{
    "success": true,
    "message": "تم التسجيل بنجاح",
    "data": {
        "user": {
            "id": 1,
            "name": "أحمد محمد",
            "email": "ahmed@example.com",
            "role": "user",
            "user_limit": {
                "subscription_name": "الخطة الأساسية",
                "limits": {
                    "customers": { "from": 0, "to": 100 },
                    "installments": { "from": 0, "to": 200 },
                    "notifications": { "from": 0, "to": 1000 }
                },
                "usage": {
                    "customers_used": 0,
                    "installments_used": 0,
                    "notifications_used": 0
                },
                "remaining": {
                    "customers": 100,
                    "installments": 200,
                    "notifications": 1000
                }
            }
        },
        "token": "1|xxxxxxxxxxxx",
        "token_type": "Bearer"
    }
}
```

### `POST /api/auth/login` (Public)

Login and get authentication token.

**Request Body:**

```json
{
    "email": "ahmed@example.com",
    "password": "password123"
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح",
  "data": {
    "user": { ... },
    "token": "1|xxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}
```

### `GET /api/auth/me` (Protected)

Get current authenticated user data.

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "تم جلب البيانات بنجاح",
  "data": {
    "user": { ... }
  }
}
```

### `POST /api/auth/logout` (Protected)

Logout and revoke token.

**Response (200 OK):**

```json
{
    "success": true,
    "message": "تم تسجيل الخروج بنجاح"
}
```

---

## 📦 Subscription Plans Endpoints

### `GET /api/subscriptions-public` (Public)

Get list of all active subscription plans.

**Response (200 OK):**

```json
{
    "success": true,
    "message": "تم جلب خطط الاشتراك بنجاح",
    "data": [
        {
            "id": 1,
            "name": "الخطة المجانية",
            "slug": "free",
            "currency": "EGP",
            "price": 0,
            "duration": "monthly",
            "description": "خطة البداية",
            "is_active": true,
            "customers": { "from": 0, "to": 10 },
            "installments": { "from": 0, "to": 20 },
            "notifications": { "from": 0, "to": 200 },
            "reports": true,
            "features": { "basic_reports": true }
        }
    ]
}
```

### `POST /api/subscriptions/{subscription}/change` (Protected)

Change user's subscription (upgrade/downgrade). Preserves current usage.

**Request Body (All fields optional):**

```json
{
    "start_date": "2025-01-01",
    "end_date": "2025-02-01",
    "status": "active",
    "features": { "custom": true }
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "message": "تم تغيير الاشتراك بنجاح",
  "data": {
    "id": 10,
    "user_id": 1,
    "subscription_name": "الخطة الذهبية",
    "limits": { ... },
    "usage": { "customers_used": 12, ... },
    "remaining": { ... }
  }
}
```

### `GET /api/subscriptions-admin` (Owner Only)

Get all subscription plans (active and inactive) with pagination.

**Query Parameters:**

-   `per_page` (optional, default: 15)

### `POST /api/subscriptions-create` (Owner Only)

Create a new subscription plan.

**Request Body:**

```json
{
    "name": "الخطة الذهبية",
    "slug": "gold-plan",
    "currency": "EGP",
    "price": 499.99,
    "duration": "monthly",
    "description": "وصف الخطة",
    "customers": { "from": 0, "to": 200 },
    "installments": { "from": 0, "to": 500 },
    "notifications": { "from": 0, "to": 5000 },
    "reports": true,
    "features": { "priority_support": true },
    "is_active": true
}
```

### `POST /api/subscriptions/{subscription}/assign` (Owner Only)

Assign a subscription plan to a user.

**Request Body:**

```json
{
    "user_id": 12,
    "start_date": "2025-01-01",
    "end_date": "2025-02-01",
    "status": "active",
    "features": { "custom": true }
}
```

---

## 📊 User Limits Endpoints

### `GET /api/limits/current` (Protected)

Get current user limits and usage.

**Response (200 OK):**

```json
{
    "success": true,
    "message": "تم جلب الحدود بنجاح",
    "data": {
        "limits": {
            "customers": { "from": 0, "to": 100 },
            "installments": { "from": 0, "to": 200 },
            "notifications": { "from": 0, "to": 1000 }
        },
        "usage": {
            "customers_used": 12,
            "installments_used": 34,
            "notifications_used": 50
        },
        "remaining": {
            "customers": 88,
            "installments": 166,
            "notifications": 950
        }
    }
}
```

### `GET /api/limits/can-create/{resourceType}` (Protected)

Check if user can create a resource. Values: `customers`, `installments`, `notifications`

**Response (200 OK):**

```json
{
    "success": true,
    "message": "يمكن إنشاء المورد",
    "data": {
        "can_create": true,
        "remaining": 88
    }
}
```

### `POST /api/limits/refresh` (Protected)

Recalculate usage counts from database.

---

## 👥 Customer Endpoints (Protected + Active Subscription Required)

### `GET /api/customer-list` (Protected + Subscription)

Get list of all customers with pagination.

**Response (200 OK):**

```json
{
  "success": true,
  "message": "تم جلب العملاء بنجاح",
  "data": {
    "data": [
      {
        "id": 1,
        "name": "محمد أحمد",
        "email": "mohamed@example.com",
        "phone": "+201000000000",
        "address": "القاهرة",
        "notes": "ملاحظات",
        "created_at": "2025-01-01T10:20:30.000000Z"
      }
    ],
    "links": { ... },
    "meta": { ... }
  }
}
```

### `POST /api/customer-create` (Protected + Subscription)

Create a new customer. Checks customer limit.

**Request Body:**

```json
{
    "name": "محمد أحمد",
    "email": "mohamed@example.com",
    "phone": "+201000000000",
    "address": "القاهرة",
    "notes": "ملاحظات"
}
```

**Fields:**

-   `name` (required, string)
-   `email` (optional, email)
-   `phone` (optional, string)
-   `address` (optional, string)
-   `notes` (optional, string)

**Error Response (403):**

```json
{
    "success": false,
    "message": "لقد وصلت إلى الحد الأقصى لعدد العملاء المسموح به في خطتك."
}
```

### `GET /api/customer-show/{id}` (Protected + Subscription)

Get customer details with installments.

### `PUT /api/customer-update/{id}` (Protected + Subscription)

Update customer information.

### `DELETE /api/customer-delete/{id}` (Protected + Subscription)

Delete a customer. Usage count is automatically decremented.

### `GET /api/customer-stats/{id}` (Protected + Subscription)

Get customer statistics (total installments, paid, remaining).

---

## 💰 Installment Endpoints (Protected + Active Subscription Required)

### `GET /api/installment-list` (Protected + Subscription)

Get list of all installments with pagination.

### `POST /api/installment-create` (Protected + Subscription)

Create a new installment plan. Checks installment limit.

**Request Body:**

```json
{
    "customer_id": 12,
    "total_amount": 10000,
    "months": 12,
    "start_date": "2025-01-01",
    "products": [
        {
            "name": "منتج أ",
            "qty": 2,
            "price": 5000
        }
    ],
    "notes": "ملاحظات"
}
```

**Fields:**

-   `customer_id` (required, integer, exists in customers)
-   `total_amount` (required, numeric, min: 0.01)
-   `months` (required, integer, 1-120)
-   `start_date` (required, date)
-   `products` (required, array, min: 1 item)
    -   `products[].name` (required, string)
    -   `products[].qty` (required, integer, min: 1)
    -   `products[].price` (required, numeric, min: 0)
-   `notes` (optional, string)

**Error Response (403):**

```json
{
    "success": false,
    "message": "لقد وصلت إلى الحد الأقصى لعدد الأقساط المسموح بها في خطتك."
}
```

### `GET /api/installment-show/{id}` (Protected + Subscription)

Get installment details.

### `GET /api/installment-overdue` (Protected + Subscription)

Get list of overdue installments.

### `GET /api/installment-due-soon` (Protected + Subscription)

Get list of installments due within 7 days.

### `POST /api/installment-item-pay/{item}` (Protected + Subscription)

Mark an installment item as paid.

**Request Body:**

```json
{
    "paid_amount": 500,
    "reference": "رقم المرجع"
}
```

### `GET /api/installment-stats/{id}` (Protected + Subscription)

Get installment statistics.

### `GET /api/installment-all-stats` (Protected + Subscription)

Get comprehensive statistics for all installments.

---

## 🔔 Notification Endpoints (Protected + Active Subscription Required)

### `GET /api/notification-list` (Protected + Subscription)

Get list of all notifications.

**Query Parameters:**

-   `unread_only` (optional, boolean)

### `GET /api/notification-count` (Protected + Subscription)

Get count of unread notifications.

### `POST /api/notification-mark-read/{id}` (Protected + Subscription)

Mark a notification as read.

### `POST /api/notification-mark-all-read` (Protected + Subscription)

Mark all notifications as read.

### `POST /api/notification-generate` (Protected + Subscription)

Generate reminder notifications for due installments. Checks notification limit.

### `POST /api/notification-send-emails` (Protected + Subscription)

Send reminder emails for due installments.

### `DELETE /api/notification-delete/{id}` (Protected + Subscription)

Delete a notification.

---

## 📊 Dashboard Endpoints

### `GET /api/dashboard` (Protected + Active Subscription Required)

Get dashboard statistics.

**Response (200 OK):**

```json
{
  "success": true,
  "message": "تم جلب البيانات بنجاح",
  "data": {
    "overdue_count": 5,
    "due_soon_count": 10,
    "total_outstanding": 50000,
    "upcoming_payments": [ ... ]
  }
}
```

---

## 👤 User Management Endpoints (Owner Only)

### `GET /api/user-list` (Owner Only)

Get list of all users with pagination.

### `POST /api/user-create` (Owner Only)

Create a new user.

**Request Body:**

```json
{
    "name": "مستخدم جديد",
    "email": "user@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "user"
}
```

### `GET /api/user-show/{id}` (Owner Only)

Get user details.

### `PUT /api/user-update/{id}` (Owner Only)

Update user information.

### `DELETE /api/user-delete/{id}` (Owner Only)

Delete a user.

---

## 📖 Full Documentation

**Interactive API documentation available at:**

-   Web UI: `http://localhost:8000/documentation/api`
-   Markdown: See `API_RESPONSES.md` for detailed request/response examples

---

## 🔑 Authentication Requirements

| Badge               | Description                                     |
| ------------------- | ----------------------------------------------- |
| 🌐 **Public**       | No authentication required                      |
| 🔒 **Protected**    | Requires `Authorization: Bearer {token}` header |
| 👑 **Owner**        | Requires authentication + Owner role            |
| ⚠️ **Subscription** | Requires authentication + Active subscription   |

---

## ⚠️ Error Responses

### Limit Exceeded (403)

```json
{
    "success": false,
    "message": "لقد وصلت إلى الحد الأقصى لعدد العملاء المسموح به في خطتك."
}
```

### Unauthorized (401)

```json
{
    "success": false,
    "message": "غير مصرح"
}
```

### Forbidden (403)

```json
{
    "success": false,
    "message": "ممنوع الوصول"
}
```

### Not Found (404)

```json
{
    "success": false,
    "message": "المورد غير موجود"
}
```

### Validation Error (422)

```json
{
    "success": false,
    "message": "بيانات غير صحيحة",
    "errors": {
        "email": ["البريد الإلكتروني مطلوب"]
    }
}
```

---

## 🛠️ Tech Stack

-   **Framework**: Laravel 12
-   **PHP**: 8.2+
-   **Authentication**: Laravel Sanctum
-   **Database**: MySQL 8.0+
-   **Architecture**: SOLID Principles
-   **API Style**: RESTful
-   **Response Format**: JSON

---

## 📁 Project Structure

```
installment_back/
├── app/
│   ├── Contracts/Services/       # Service interfaces (SOLID - DIP)
│   │   ├── AuthServiceInterface.php
│   │   ├── CustomerServiceInterface.php
│   │   ├── InstallmentServiceInterface.php
│   │   └── UserServiceInterface.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/              # Clean API controllers (SOLID - SRP)
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── CustomerController.php
│   │   │   │   ├── InstallmentController.php
│   │   │   │   └── UserController.php
│   │   │   └── DocumentationController.php
│   │   ├── Middleware/           # Custom middleware
│   │   │   └── EnsureOwner.php
│   │   ├── Requests/             # Form request validation
│   │   │   ├── StoreCustomerRequest.php
│   │   │   └── StoreInstallmentRequest.php
│   │   ├── Resources/            # API resource transformers (SOLID - SRP)
│   │   │   ├── CustomerResource.php
│   │   │   ├── InstallmentItemResource.php
│   │   │   ├── InstallmentResource.php
│   │   │   └── UserResource.php
│   │   └── Traits/
│   │       └── ApiResponse.php   # Consistent API responses
│   ├── Models/                   # Eloquent models
│   │   ├── Customer.php
│   │   ├── Installment.php
│   │   ├── InstallmentItem.php
│   │   └── User.php
│   ├── Services/                 # Business logic (SOLID - SRP + ISP)
│   │   ├── AuthService.php
│   │   ├── CustomerService.php
│   │   ├── InstallmentService.php
│   │   └── UserService.php
│   └── Providers/
│       └── ServiceBindingProvider.php  # Dependency injection (SOLID - DIP)
├── routes/
│   ├── api.php                   # API routes
│   └── web.php                   # Web routes (documentation)
├── database/
│   ├── migrations/               # Database migrations
│   └── seeders/                  # Database seeders
├── resources/
│   └── views/
│       └── documentation/        # Beautiful API documentation page
│           └── index.blade.php
├── tests/                        # Application tests
├── API_DOCUMENTATION.md          # Complete API reference
├── SOLID_PRINCIPLES.md           # Architecture guide
├── SETUP_GUIDE.md               # Detailed setup instructions
├── PROJECT_SUMMARY.md           # Project overview
└── QUICK_START.md               # Get started in 5 minutes
```

---

## 💻 Development

### Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### View All Routes

```bash
php artisan route:list
```

### Database Commands

```bash
# Reset database
php artisan migrate:fresh

# Reset with seeders
php artisan migrate:fresh --seed

# Create migration
php artisan make:migration create_table_name

# Create seeder
php artisan make:seeder TableNameSeeder
```

### Code Quality

```bash
# Run PHP linter
composer lint

# Format code
php artisan pint
```

---

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=CustomerTest

# Run with coverage
php artisan test --coverage

# Run in parallel
php artisan test --parallel
```

---

## 📖 Documentation

This project includes comprehensive documentation:

1. **[QUICK_START.md](QUICK_START.md)** - Get running in 5 minutes
2. **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)** - Complete API reference with examples
3. **[API_RESPONSES.md](API_RESPONSES.md)** - Detailed API request/response examples
4. **[SOLID_PRINCIPLES.md](SOLID_PRINCIPLES.md)** - Architecture and design patterns explained
5. **[SETUP_GUIDE.md](SETUP_GUIDE.md)** - Detailed setup and configuration
6. **[PROJECT_SUMMARY.md](PROJECT_SUMMARY.md)** - Project overview and transformation details
7. **[Web Documentation](http://localhost:8000/documentation)** - Overview page
8. **[Full API Documentation](http://localhost:8000/documentation/api)** - Complete interactive API documentation with all routes, requests, and responses (run `php artisan serve` first)

---

## 🎨 Key Features

✅ **SOLID Architecture** - Clean, maintainable, and scalable code  
✅ **Type Safety** - Strict typing throughout the application  
✅ **Dependency Injection** - Interface-based programming  
✅ **Consistent API Responses** - Standardized JSON format  
✅ **Comprehensive Error Handling** - Meaningful error messages  
✅ **Token Authentication** - Secure with Laravel Sanctum  
✅ **Role-Based Access** - Owner and User roles  
✅ **Pagination Support** - Efficient data handling  
✅ **Resource Transformers** - Clean API responses  
✅ **Form Request Validation** - Secure input validation  
✅ **Database Transactions** - Data integrity  
✅ **Well Documented** - Multiple documentation formats

---

## 🔒 Security

-   Token-based authentication
-   Password hashing with bcrypt
-   SQL injection prevention (Eloquent ORM)
-   CSRF protection
-   XSS protection
-   Rate limiting
-   Input validation
-   Authorization checks

---

## 🤝 Contributing

We welcome contributions! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Follow SOLID principles
4. Write tests for new features
5. Use type hints and return types
6. Document your code
7. Follow PSR-12 coding standards
8. Commit your changes (`git commit -m 'Add amazing feature'`)
9. Push to the branch (`git push origin feature/amazing-feature`)
10. Open a Pull Request

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 👨‍💻 Author

Built with ❤️ following enterprise-level best practices and SOLID principles.

---

## 🌟 Show Your Support

Give a ⭐️ if this project helped you!

---

## 📞 Support

For issues, questions, or contributions:

-   📧 Email: support@example.com
-   🐛 Issues: [GitHub Issues](https://github.com/yourusername/installment_back/issues)
-   📖 Docs: [http://localhost:8000/documentation](http://localhost:8000/documentation)

---

<p align="center">Made with Laravel 12 🚀</p>
