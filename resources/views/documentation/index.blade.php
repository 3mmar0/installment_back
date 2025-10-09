<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installment Manager API - Documentation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            color: #666;
        }

        .badge {
            display: inline-block;
            padding: 5px 15px;
            background: #667eea;
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            margin: 10px 5px 0;
            font-weight: 600;
        }

        .content {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            align-items: start;
        }

        .sidebar {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }

        .sidebar h3 {
            font-size: 1rem;
            color: #667eea;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar ul {
            list-style: none;
        }

        .sidebar ul li {
            margin-bottom: 8px;
        }

        .sidebar ul li a {
            color: #666;
            text-decoration: none;
            display: block;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .sidebar ul li a:hover {
            background: #f0f4ff;
            color: #667eea;
            padding-left: 16px;
        }

        .main-content {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .section {
            margin-bottom: 50px;
            scroll-margin-top: 20px;
        }

        .section h2 {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #f0f4ff;
        }

        .section h3 {
            font-size: 1.5rem;
            color: #764ba2;
            margin: 30px 0 15px;
        }

        .section h4 {
            font-size: 1.2rem;
            color: #555;
            margin: 20px 0 10px;
        }

        .endpoint {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }

        .endpoint-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .method {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .method.post {
            background: #28a745;
            color: white;
        }

        .method.get {
            background: #007bff;
            color: white;
        }

        .method.put {
            background: #ffc107;
            color: #333;
        }

        .method.delete {
            background: #dc3545;
            color: white;
        }

        .endpoint-url {
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            color: #333;
            font-weight: 600;
        }

        .code-block {
            background: #282c34;
            color: #abb2bf;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .code-block.json {
            color: #98c379;
        }

        .solid-principles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .principle-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .principle-card h4 {
            color: white;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .principle-card p {
            font-size: 0.9rem;
            opacity: 0.95;
        }

        .status-codes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .status-code {
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
        }

        .status-code.success {
            background: #d4edda;
            color: #155724;
        }

        .status-code.error {
            background: #f8d7da;
            color: #721c24;
        }

        .status-code.info {
            background: #d1ecf1;
            color: #0c5460;
        }

        ul.features {
            list-style: none;
            padding-left: 0;
        }

        ul.features li {
            padding: 10px 0;
            padding-left: 30px;
            position: relative;
        }

        ul.features li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .highlight {
            background: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            border-radius: 6px;
            margin: 15px 0;
        }

        @media (max-width: 968px) {
            .content {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
                max-height: none;
            }

            .header h1 {
                font-size: 2rem;
            }
        }

        .footer {
            text-align: center;
            color: white;
            padding: 30px 0;
            margin-top: 40px;
        }

        .response-example {
            margin: 15px 0;
        }

        .response-label {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 8px;
        }

        a {
            color: #667eea;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Installment Manager API</h1>
            <p>RESTful API for managing installment plans and customer payments</p>
            <div>
                <span class="badge">Laravel 12</span>
                <span class="badge">SOLID Principles</span>
                <span class="badge">RESTful</span>
                <span class="badge">Sanctum Auth</span>
            </div>
        </div>

        <div class="content">
            <aside class="sidebar">
                <h3>Navigation</h3>
                <ul>
                    <li><a href="#overview">Overview</a></li>
                    <li><a href="#architecture">Architecture</a></li>
                    <li><a href="#authentication">Authentication</a></li>
                    <li><a href="#customers">Customers</a></li>
                    <li><a href="#installments">Installments</a></li>
                    <li><a href="#dashboard">Dashboard</a></li>
                    <li><a href="#users">Users</a></li>
                    <li><a href="#errors">Error Handling</a></li>
                    <li><a href="#setup">Development Setup</a></li>
                </ul>
            </aside>

            <main class="main-content">
                <!-- Overview Section -->
                <section id="overview" class="section">
                    <h2>Overview</h2>
                    <p>This is a RESTful API for managing installment plans and customer payments. The API is built with
                        Laravel 12 following SOLID principles and best practices.</p>

                    <div class="highlight">
                        <strong>Base URL:</strong> <code>{{ url('/api') }}</code>
                    </div>
                </section>

                <!-- Architecture Section -->
                <section id="architecture" class="section">
                    <h2>Architecture</h2>
                    <h3>SOLID Principles Implementation</h3>

                    <div class="solid-principles">
                        <div class="principle-card">
                            <h4>Single Responsibility</h4>
                            <p>Each class has one clear purpose: Controllers handle HTTP, Services contain business
                                logic, Resources transform data.</p>
                        </div>
                        <div class="principle-card">
                            <h4>Open/Closed</h4>
                            <p>Services are open for extension through interfaces without modifying existing code.</p>
                        </div>
                        <div class="principle-card">
                            <h4>Liskov Substitution</h4>
                            <p>All services implement interfaces and can be substituted without breaking functionality.
                            </p>
                        </div>
                        <div class="principle-card">
                            <h4>Interface Segregation</h4>
                            <p>Separate focused interfaces for Auth, User, Customer, and Installment services.</p>
                        </div>
                        <div class="principle-card">
                            <h4>Dependency Inversion</h4>
                            <p>Controllers depend on interfaces, not concrete implementations via
                                ServiceBindingProvider.</p>
                        </div>
                    </div>
                </section>

                <!-- Authentication Section -->
                <section id="authentication" class="section">
                    <h2>Authentication</h2>
                    <p>The API uses Laravel Sanctum for token-based authentication.</p>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/auth/register</span>
                        </div>
                        <p><strong>Register a new user</strong></p>
                        <div class="code-block">POST /api/auth/register
                            Content-Type: application/json

                            {
                            "name": "John Doe",
                            "email": "john@example.com",
                            "password": "password123",
                            "password_confirmation": "password123"
                            }</div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/auth/login</span>
                        </div>
                        <p><strong>Login and get access token</strong></p>
                        <div class="code-block">POST /api/auth/login
                            Content-Type: application/json

                            {
                            "email": "john@example.com",
                            "password": "password123"
                            }</div>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block json">{
                                "success": true,
                                "message": "Login successful",
                                "data": {
                                "user": {...},
                                "token": "2|xxxxxxxxxxxxxxxxxxxxx",
                                "token_type": "Bearer"
                                }
                                }</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/auth/me</span>
                        </div>
                        <p><strong>Get authenticated user</strong></p>
                        <div class="code-block">GET /api/auth/me
                            Authorization: Bearer {token}</div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/auth/logout</span>
                        </div>
                        <p><strong>Logout and revoke tokens</strong></p>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/auth/refresh</span>
                        </div>
                        <p><strong>Refresh access token</strong></p>
                    </div>
                </section>

                <!-- Customers Section -->
                <section id="customers" class="section">
                    <h2>Customers</h2>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/customers</span>
                        </div>
                        <p><strong>List all customers (paginated)</strong></p>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/customers</span>
                        </div>
                        <p><strong>Create a new customer</strong></p>
                        <div class="code-block">POST /api/customers
                            Authorization: Bearer {token}
                            Content-Type: application/json

                            {
                            "name": "Jane Smith",
                            "email": "jane@example.com",
                            "phone": "+1234567890",
                            "address": "123 Main St",
                            "notes": "VIP Customer"
                            }</div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/customers/{id}</span>
                        </div>
                        <p><strong>Get customer details</strong></p>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method put">PUT</span>
                            <span class="endpoint-url">/api/customers/{id}</span>
                        </div>
                        <p><strong>Update customer</strong></p>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method delete">DELETE</span>
                            <span class="endpoint-url">/api/customers/{id}</span>
                        </div>
                        <p><strong>Delete customer</strong></p>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/customers/{id}/stats</span>
                        </div>
                        <p><strong>Get customer statistics</strong></p>
                    </div>
                </section>

                <!-- Installments Section -->
                <section id="installments" class="section">
                    <h2>Installments</h2>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/installments</span>
                        </div>
                        <p><strong>List all installments (paginated)</strong></p>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/installments</span>
                        </div>
                        <p><strong>Create a new installment plan</strong></p>
                        <div class="code-block">POST /api/installments
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
                            }</div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/installments/{id}</span>
                        </div>
                        <p><strong>Get installment details</strong></p>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/installment-items/{item}/pay</span>
                        </div>
                        <p><strong>Mark installment item as paid</strong></p>
                        <div class="code-block">POST /api/installment-items/{item_id}/pay
                            Authorization: Bearer {token}
                            Content-Type: application/json

                            {
                            "paid_amount": 100.00,
                            "reference": "PAYMENT-001"
                            }</div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/installments/overdue</span>
                        </div>
                        <p><strong>Get overdue installment items</strong></p>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/installments/due-soon</span>
                        </div>
                        <p><strong>Get items due soon (within 7 days)</strong></p>
                    </div>
                </section>

                <!-- Dashboard Section -->
                <section id="dashboard" class="section">
                    <h2>Dashboard</h2>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/dashboard</span>
                        </div>
                        <p><strong>Get dashboard analytics</strong></p>
                        <div class="response-example">
                            <div class="response-label">Returns:</div>
                            <ul class="features">
                                <li>Due soon count</li>
                                <li>Overdue count</li>
                                <li>Outstanding amount</li>
                                <li>Collected this month</li>
                                <li>Upcoming payments</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Users Section -->
                <section id="users" class="section">
                    <h2>Users (Owner Only)</h2>
                    <p class="highlight">These endpoints require owner role.</p>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/users</span>
                        </div>
                        <p><strong>List all users</strong></p>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/users</span>
                        </div>
                        <p><strong>Create a new user</strong></p>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/users/{id}</span>
                        </div>
                        <p><strong>Get user details</strong></p>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method put">PUT</span>
                            <span class="endpoint-url">/api/users/{id}</span>
                        </div>
                        <p><strong>Update user</strong></p>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method delete">DELETE</span>
                            <span class="endpoint-url">/api/users/{id}</span>
                        </div>
                        <p><strong>Delete user</strong></p>
                    </div>
                </section>

                <!-- Error Handling Section -->
                <section id="errors" class="section">
                    <h2>Error Handling</h2>
                    <p>All errors follow a consistent JSON format:</p>
                    <div class="code-block json">{
                        "success": false,
                        "message": "Error message",
                        "errors": {
                        "field": ["Error details"]
                        }
                        }</div>

                    <h3>HTTP Status Codes</h3>
                    <div class="status-codes">
                        <div class="status-code success">200 - Success</div>
                        <div class="status-code success">201 - Created</div>
                        <div class="status-code error">400 - Bad Request</div>
                        <div class="status-code error">401 - Unauthorized</div>
                        <div class="status-code error">403 - Forbidden</div>
                        <div class="status-code error">404 - Not Found</div>
                        <div class="status-code error">422 - Validation Error</div>
                        <div class="status-code error">500 - Server Error</div>
                    </div>
                </section>

                <!-- Setup Section -->
                <section id="setup" class="section">
                    <h2>Development Setup</h2>

                    <h3>Quick Start</h3>
                    <div class="code-block"># Install dependencies
                        composer install

                        # Setup environment
                        cp .env.example .env
                        php artisan key:generate

                        # Configure database in .env file

                        # Run migrations
                        php artisan migrate

                        # Start server
                        php artisan serve</div>

                    <h3>Environment Variables</h3>
                    <div class="code-block">APP_NAME="Installment Manager"
                        APP_ENV=local
                        APP_DEBUG=true
                        APP_URL=http://localhost

                        DB_CONNECTION=mysql
                        DB_HOST=127.0.0.1
                        DB_PORT=3306
                        DB_DATABASE=installment_manager
                        DB_USERNAME=root
                        DB_PASSWORD=

                        SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1</div>

                    <h3>Key Features</h3>
                    <ul class="features">
                        <li>Token-based authentication (Laravel Sanctum)</li>
                        <li>Customer management with CRUD operations</li>
                        <li>Installment plans with automatic payment scheduling</li>
                        <li>Dashboard analytics and metrics</li>
                        <li>Overdue payment tracking</li>
                        <li>Role-based access control (Owner/User)</li>
                        <li>Consistent JSON API responses</li>
                        <li>Comprehensive error handling</li>
                    </ul>
                </section>
            </main>
        </div>

        <footer class="footer">
            <p>Built with Laravel 12 following SOLID principles</p>
            <p>© {{ date('Y') }} Installment Manager API</p>
        </footer>
    </div>
</body>

</html>
