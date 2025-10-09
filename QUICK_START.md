# Quick Start Guide

## 🚀 Get Started in 5 Minutes

### Step 1: Install Dependencies

```bash
composer install
```

### Step 2: Setup Environment

```bash
cp .env.example .env
php artisan key:generate
```

### Step 3: Configure Database

Edit `.env`:

```env
DB_DATABASE=installment_manager
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Step 4: Run Migrations

```bash
php artisan migrate
```

### Step 5: Start Server

```bash
php artisan serve
```

## 🧪 Test the API

### 1. Register a User

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

**Save the token from the response!**

### 2. Get Your Profile

```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### 3. Create a Customer

```bash
curl -X POST http://localhost:8000/api/customers \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890"
  }'
```

### 4. View Dashboard

```bash
curl -X GET http://localhost:8000/api/dashboard \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## 📖 Full Documentation

-   **API Reference**: See `API_DOCUMENTATION.md`
-   **Setup Guide**: See `SETUP_GUIDE.md`
-   **Architecture**: See `SOLID_PRINCIPLES.md`
-   **Project Overview**: See `PROJECT_SUMMARY.md`

## 🎯 Key Features

✅ Token-based authentication (Laravel Sanctum)
✅ Customer management
✅ Installment plans with automatic payment scheduling
✅ Dashboard analytics
✅ Overdue payment tracking
✅ Role-based access control (Owner/User)

## 📚 API Endpoints Summary

### Auth

-   `POST /api/auth/register` - Register new user
-   `POST /api/auth/login` - Login
-   `GET /api/auth/me` - Get current user
-   `POST /api/auth/logout` - Logout

### Customers

-   `GET /api/customers` - List customers
-   `POST /api/customers` - Create customer
-   `GET /api/customers/{id}` - View customer
-   `PUT /api/customers/{id}` - Update customer
-   `DELETE /api/customers/{id}` - Delete customer

### Installments

-   `GET /api/installments` - List installments
-   `POST /api/installments` - Create installment
-   `GET /api/installments/{id}` - View installment
-   `POST /api/installment-items/{item}/pay` - Mark as paid

### Dashboard

-   `GET /api/dashboard` - Get analytics

## 🔐 Authentication

All protected endpoints require:

```
Authorization: Bearer YOUR_TOKEN_HERE
```

## 💡 Tips

1. Use Postman for easier testing
2. Check `routes/api.php` for all endpoints
3. All API routes are prefixed with `/api`
4. Responses are always JSON
5. Errors include helpful messages

## ⚡ Quick Commands

```bash
# Clear cache
php artisan config:clear && php artisan cache:clear

# View all routes
php artisan route:list

# Run tests
php artisan test

# Reset database
php artisan migrate:fresh
```

Happy coding! 🎉
