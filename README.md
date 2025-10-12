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
    http://localhost:8000/documentation
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

### Main Endpoints

| Method | Endpoint                            | Description             |
| ------ | ----------------------------------- | ----------------------- |
| POST   | `/api/auth/register`                | Register new user       |
| POST   | `/api/auth/login`                   | Login and get token     |
| GET    | `/api/auth/me`                      | Get current user        |
| POST   | `/api/auth/logout`                  | Logout                  |
| GET    | `/api/dashboard`                    | Get dashboard analytics |
| GET    | `/api/customers`                    | List customers          |
| POST   | `/api/customers`                    | Create customer         |
| GET    | `/api/customers/{id}`               | Get customer            |
| PUT    | `/api/customers/{id}`               | Update customer         |
| DELETE | `/api/customers/{id}`               | Delete customer         |
| GET    | `/api/installments`                 | List installments       |
| POST   | `/api/installments`                 | Create installment      |
| GET    | `/api/installments/{id}`            | Get installment         |
| POST   | `/api/installment-items/{item}/pay` | Mark as paid            |
| GET    | `/api/installments/overdue`         | Get overdue items       |
| GET    | `/api/installments/due-soon`        | Get due soon items      |

**Full API documentation available at:** `http://localhost:8000/documentation`

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
3. **[SOLID_PRINCIPLES.md](SOLID_PRINCIPLES.md)** - Architecture and design patterns explained
4. **[SETUP_GUIDE.md](SETUP_GUIDE.md)** - Detailed setup and configuration
5. **[PROJECT_SUMMARY.md](PROJECT_SUMMARY.md)** - Project overview and transformation details
6. **[Web Documentation](http://localhost:8000/documentation)** - Beautiful interactive documentation (run `php artisan serve` first)

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
