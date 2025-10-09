# Installment Manager API - Setup Guide

## Overview

This guide will help you set up and run the Installment Manager API on your local machine.

## Prerequisites

-   PHP 8.2 or higher
-   Composer
-   MySQL 8.0 or higher
-   Git

## Installation Steps

### 1. Clone the Repository

```bash
git clone <repository-url>
cd installment_back
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Configuration

```bash
# Copy the environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Configure Database

Edit `.env` file with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=installment_manager
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 5. Run Migrations

```bash
# Create database tables
php artisan migrate

# (Optional) Seed database with sample data
php artisan db:seed
```

### 6. Publish Sanctum Configuration (if needed)

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### 7. Start Development Server

```bash
php artisan serve
```

The API will be available at: `http://localhost:8000`

## Testing the API

### 1. Register a New User

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### 2. Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

Save the token from the response.

### 3. Create a Customer

```bash
curl -X POST http://localhost:8000/api/customers \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "name": "Jane Smith",
    "email": "jane@example.com",
    "phone": "+1234567890",
    "address": "123 Main St"
  }'
```

### 4. Create an Installment

```bash
curl -X POST http://localhost:8000/api/installments \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "customer_id": 1,
    "total_amount": 1200,
    "products": [
      {"name": "Laptop", "qty": 1, "price": 1000},
      {"name": "Mouse", "qty": 1, "price": 200}
    ],
    "start_date": "2024-01-01",
    "months": 12,
    "notes": "Monthly payment plan"
  }'
```

### 5. Get Dashboard Analytics

```bash
curl -X GET http://localhost:8000/api/dashboard \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## Using Postman

1. Import the API endpoints into Postman
2. Create an environment variable `token` for authentication
3. Set the `Authorization` header to `Bearer {{token}}`
4. All endpoints are prefixed with `/api`

## Common Issues and Solutions

### Issue: "SQLSTATE[HY000] [2002] Connection refused"

**Solution:** Make sure MySQL is running and database credentials in `.env` are correct.

### Issue: "Class 'Laravel\Sanctum\HasApiTokens' not found"

**Solution:** Run `composer install` again to ensure Sanctum is installed.

### Issue: "Unauthenticated" error on protected routes

**Solution:** Make sure you're sending the Bearer token in the Authorization header.

### Issue: Migration errors

**Solution:** Drop all tables and run migrations again:

```bash
php artisan migrate:fresh
```

## Development Tips

### Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### View Routes

```bash
php artisan route:list
```

### Run Tests

```bash
php artisan test
```

### Database Management

#### Reset Database

```bash
php artisan migrate:fresh --seed
```

#### Create New Migration

```bash
php artisan make:migration create_table_name
```

#### Create New Seeder

```bash
php artisan make:seeder TableNameSeeder
```

## API Features

### Authentication

-   ✅ User registration
-   ✅ User login
-   ✅ Token-based authentication (Laravel Sanctum)
-   ✅ User logout
-   ✅ Token refresh
-   ✅ Get authenticated user

### Customer Management

-   ✅ List customers (with pagination)
-   ✅ Create customer
-   ✅ View customer details
-   ✅ Update customer
-   ✅ Delete customer
-   ✅ Get customer statistics

### Installment Management

-   ✅ List installments (with pagination)
-   ✅ Create installment
-   ✅ View installment details
-   ✅ Automatic installment item generation
-   ✅ Mark installment item as paid
-   ✅ Get overdue items
-   ✅ Get due soon items

### Dashboard & Analytics

-   ✅ Dashboard with key metrics
-   ✅ Due soon count
-   ✅ Overdue count
-   ✅ Outstanding amount
-   ✅ Collected this month
-   ✅ Upcoming payments

### User Management (Owner Only)

-   ✅ List users
-   ✅ Create user
-   ✅ View user details
-   ✅ Update user
-   ✅ Delete user

## Project Structure

```
installment_back/
├── app/
│   ├── Contracts/Services/       # Service interfaces (SOLID DIP)
│   ├── Http/
│   │   ├── Controllers/Api/      # API Controllers
│   │   ├── Middleware/           # Custom middleware
│   │   ├── Requests/             # Form requests
│   │   ├── Resources/            # API resources
│   │   └── Traits/               # Reusable traits
│   ├── Models/                   # Eloquent models
│   ├── Services/                 # Business logic services
│   └── Providers/                # Service providers
├── bootstrap/
│   ├── app.php                   # Application bootstrap
│   └── providers.php             # Service provider registration
├── config/                       # Configuration files
├── database/
│   ├── migrations/               # Database migrations
│   └── seeders/                  # Database seeders
├── routes/
│   ├── api.php                   # API routes
│   └── web.php                   # Web routes (minimal)
├── tests/                        # Application tests
├── .env                          # Environment variables
├── composer.json                 # PHP dependencies
├── API_DOCUMENTATION.md          # API documentation
├── SOLID_PRINCIPLES.md           # SOLID principles guide
└── SETUP_GUIDE.md               # This file
```

## Next Steps

1. Read the [API Documentation](API_DOCUMENTATION.md) for detailed endpoint information
2. Review [SOLID Principles](SOLID_PRINCIPLES.md) to understand the architecture
3. Explore the codebase starting with controllers in `app/Http/Controllers/Api`
4. Check out service implementations in `app/Services`
5. Review service interfaces in `app/Contracts/Services`

## Support

For issues or questions:

1. Check the documentation files
2. Review the code comments
3. Contact the development team

## Security Notes

-   Never commit `.env` file
-   Change `APP_KEY` in production
-   Use strong passwords
-   Enable HTTPS in production
-   Set `APP_DEBUG=false` in production
-   Configure CORS properly for production

## Production Deployment

Before deploying to production:

1. Set environment to production:

```env
APP_ENV=production
APP_DEBUG=false
```

2. Optimize the application:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

3. Set up proper database backups
4. Configure CORS for your frontend domain
5. Use queue workers for long-running tasks
6. Set up monitoring and logging

## Contributing

Follow these guidelines:

1. Follow SOLID principles
2. Write tests for new features
3. Use type hints and return types
4. Document your code
5. Follow PSR-12 coding standards

Happy coding! 🚀
