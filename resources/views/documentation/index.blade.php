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
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 15px 0;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            position: relative;
        }

        .code-block pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .code-block.json {
            background: #1e1e1e;
        }

        .code-block .comment {
            color: #6a9955;
        }

        .code-block .string {
            color: #ce9178;
        }

        .code-block .keyword {
            color: #569cd6;
        }

        .code-block .number {
            color: #b5cea8;
        }

        .request-response {
            display: grid;
            gap: 15px;
            margin: 15px 0;
        }

        .code-section {
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
        }

        .code-section-header {
            background: #667eea;
            color: white;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .code-section-body {
            padding: 15px;
            background: #1e1e1e;
        }

        .code-section-body pre {
            margin: 0;
            color: #d4d4d4;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .json-key {
            color: #9cdcfe;
        }

        .json-string {
            color: #ce9178;
        }

        .json-number {
            color: #b5cea8;
        }

        .json-boolean {
            color: #569cd6;
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

        .frontend-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 30px;
            margin: 20px 0;
        }

        .frontend-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #007bff;
        }

        .code-example {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .feature-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .feature-icon {
            font-size: 2rem;
            margin-bottom: 10px;
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
                    <li><a href="#notifications">Notifications</a></li>
                    <li><a href="#users">Users</a></li>
                    <li><a href="#errors">Error Handling</a></li>
                    <li><a href="#frontend">Frontend Integration</a></li>
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

                    <h3>🎯 Key Features</h3>
                    <div class="feature-grid">
                        <div class="feature-item">
                            <div class="feature-icon">🔐</div>
                            <h4>Token Authentication</h4>
                            <p>Laravel Sanctum for secure API access</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">👥</div>
                            <h4>Customer Management</h4>
                            <p>Full CRUD operations with statistics</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">💳</div>
                            <h4>Installment Tracking</h4>
                            <p>Payment plans with automatic scheduling</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">📊</div>
                            <h4>Dashboard Analytics</h4>
                            <p>Comprehensive business insights</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🔔</div>
                            <h4>Notifications</h4>
                            <p>In-app and email notifications</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">📧</div>
                            <h4>Arabic Emails</h4>
                            <p>Professional RTL email templates</p>
                        </div>
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
                        <div class="request-response">
                            <div class="code-section">
                                <div class="code-section-header">📤 Request</div>
                                <div class="code-section-body">
                                    <pre>POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}</pre>
                                </div>
                            </div>
                            <div class="code-section">
                                <div class="code-section-header">📥 Response (200 OK)</div>
                                <div class="code-section-body">
                                    <pre>{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "user"
    },
    "token": "2|xxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/auth/login</span>
                        </div>
                        <p><strong>Login and get access token</strong></p>
                        <div class="request-response">
                            <div class="code-section">
                                <div class="code-section-header">📤 Request</div>
                                <div class="code-section-body">
                                    <pre>POST /api/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}</pre>
                                </div>
                            </div>
                            <div class="code-section">
                                <div class="code-section-header">📥 Response (200 OK)</div>
                                <div class="code-section-body">
                                    <pre>{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "user",
      "email_verified_at": "2024-01-01T10:00:00.000000Z",
      "created_at": "2024-01-01T10:00:00.000000Z"
    },
    "token": "2|xxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/auth/me</span>
                        </div>
                        <p><strong>Get authenticated user</strong></p>
                        <div class="request-response">
                            <div class="code-section">
                                <div class="code-section-header">📤 Request</div>
                                <div class="code-section-body">
                                    <pre>GET /api/auth/me
Authorization: Bearer {token}</pre>
                                </div>
                            </div>
                            <div class="code-section">
                                <div class="code-section-header">📥 Response (200 OK)</div>
                                <div class="code-section-body">
                                    <pre>{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "user",
    "email_verified_at": "2024-01-01T10:00:00.000000Z",
    "created_at": "2024-01-01T10:00:00.000000Z"
  }
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/auth/logout</span>
                        </div>
                        <p><strong>Logout and revoke tokens</strong></p>
                        <div class="request-response">
                            <div class="code-section">
                                <div class="code-section-header">📤 Request</div>
                                <div class="code-section-body">
                                    <pre>POST /api/auth/logout
Authorization: Bearer {token}</pre>
                                </div>
                            </div>
                            <div class="code-section">
                                <div class="code-section-header">📥 Response (200 OK)</div>
                                <div class="code-section-body">
                                    <pre>{
  "success": true,
  "message": "Logged out successfully"
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/auth/refresh</span>
                        </div>
                        <p><strong>Refresh access token</strong></p>
                        <div class="request-response">
                            <div class="code-section">
                                <div class="code-section-header">📤 Request</div>
                                <div class="code-section-body">
                                    <pre>POST /api/auth/refresh
Authorization: Bearer {token}</pre>
                                </div>
                            </div>
                            <div class="code-section">
                                <div class="code-section-header">📥 Response (200 OK)</div>
                                <div class="code-section-body">
                                    <pre>{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "token": "3|xxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Customers Section -->
                <section id="customers" class="section">
                    <h2>Customers</h2>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/customer-list</span>
                        </div>
                        <p><strong>List all customers (paginated)</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Jane Smith",
        "email": "jane@example.com",
        "phone": "+1234567890",
        "address": "123 Main St",
        "notes": "VIP Customer",
        "user_id": 1,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
      }
    ],
    "per_page": 15,
    "total": 50
  }
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/customer-for-select</span>
                        </div>
                        <p><strong>Get lightweight customer list for select inputs</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Jane Smith",
      "email": "jane@example.com",
      "phone": "+1234567890",
      "label": "Jane Smith - jane@example.com"
    }
  ]
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/customer-create</span>
                        </div>
                        <p><strong>Create a new customer</strong></p>
                        <div class="request-response">
                            <div class="code-section">
                                <div class="code-section-header">📤 Request</div>
                                <div class="code-section-body">
                                    <pre>POST /api/customer-create
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "phone": "+1234567890",
  "address": "123 Main St",
  "notes": "VIP Customer"
}</pre>
                                </div>
                            </div>
                            <div class="code-section">
                                <div class="code-section-header">📥 Response (201 Created)</div>
                                <div class="code-section-body">
                                    <pre>{
  "success": true,
  "message": "Customer created successfully",
  "data": {
    "id": 1,
    "name": "Jane Smith",
    "email": "jane@example.com",
    "phone": "+1234567890",
    "address": "123 Main St",
    "notes": "VIP Customer",
    "user_id": 1,
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z"
  }
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/customer-show/{id}</span>
                        </div>
                        <p><strong>Get customer details with installments</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": {
    "id": 1,
    "name": "Jane Smith",
    "email": "jane@example.com",
    "phone": "+1234567890",
    "address": "123 Main St",
    "notes": "VIP Customer",
    "installments": [
      {
        "id": 1,
        "total_amount": "1200.00",
        "months": 12,
        "status": "active",
        "items": [...]
      }
    ]
  }
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method put">PUT</span>
                            <span class="endpoint-url">/api/customer-update/{id}</span>
                        </div>
                        <p><strong>Update customer</strong></p>
                        <div class="request-response">
                            <div class="code-section">
                                <div class="code-section-header">📤 Request</div>
                                <div class="code-section-body">
                                    <pre>PUT /api/customer-update/1
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Jane Smith Updated",
  "phone": "+9876543210",
  "address": "456 New St"
}</pre>
                                </div>
                            </div>
                            <div class="code-section">
                                <div class="code-section-header">📥 Response (200 OK)</div>
                                <div class="code-section-body">
                                    <pre>{
  "success": true,
  "message": "Customer updated successfully",
  "data": {
    "id": 1,
    "name": "Jane Smith Updated",
    "email": "jane@example.com",
    "phone": "+9876543210",
    "address": "456 New St",
    "updated_at": "2024-01-15T14:30:00.000000Z"
  }
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method delete">DELETE</span>
                            <span class="endpoint-url">/api/customer-delete/{id}</span>
                        </div>
                        <p><strong>Delete customer</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "message": "Customer deleted successfully"
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/customer-stats/{id}</span>
                        </div>
                        <p><strong>Get customer statistics</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": {
    "total_installments": 3,
    "active_installments": 2,
    "total_amount": "5000.00",
    "paid_amount": "2500.00",
    "outstanding_amount": "2500.00",
    "last_payment_date": "2024-01-15"
  }
}</div>
                        </div>
                    </div>
                </section>

                <!-- Installments Section -->
                <section id="installments" class="section">
                    <h2>Installments</h2>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/installment-list</span>
                        </div>
                        <p><strong>List all installments (paginated)</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "customer_id": 1,
        "total_amount": "1200.00",
        "months": 12,
        "status": "active",
        "created_at": "2024-01-01T10:00:00.000000Z"
      }
    ],
    "per_page": 15,
    "total": 20
  }
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/installment-create</span>
                        </div>
                        <p><strong>Create a new installment plan</strong></p>
                        <div class="request-response">
                            <div class="code-section">
                                <div class="code-section-header">📤 Request</div>
                                <div class="code-section-body">
                                    <pre>POST /api/installment-create
Authorization: Bearer {token}
Content-Type: application/json

{
  "customer_id": 1,
  "total_amount": 1200.00,
  "products": [
    {
      "name": "Laptop",
      "qty": 1,
      "price": 1000
    },
    {
      "name": "Mouse",
      "qty": 1,
      "price": 200
    }
  ],
  "start_date": "2024-01-01",
  "months": 12,
  "notes": "Monthly payment plan"
}</pre>
                                </div>
                            </div>
                            <div class="code-section">
                                <div class="code-section-header">📥 Response (201 Created)</div>
                                <div class="code-section-body">
                                    <pre>{
  "success": true,
  "message": "Installment created successfully",
  "data": {
    "id": 1,
    "customer_id": 1,
    "total_amount": "1200.00",
    "months": 12,
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "status": "active",
    "notes": "Monthly payment plan",
    "items": [
      {
        "id": 1,
        "amount": "100.00",
        "due_date": "2024-01-01",
        "status": "pending"
      }
    ],
    "created_at": "2024-01-01T10:00:00.000000Z"
  }
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/installment-show/{id}</span>
                        </div>
                        <p><strong>Get installment details</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": {
    "id": 1,
    "customer_id": 1,
    "total_amount": "1200.00",
    "months": 12,
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "status": "active",
    "items": [
      {
        "id": 1,
        "amount": "100.00",
        "due_date": "2024-01-01",
        "status": "paid",
        "paid_at": "2024-01-01T10:00:00.000000Z"
      }
    ],
    "customer": {...}
  }
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/installment-item-pay/{item}</span>
                        </div>
                        <p><strong>Mark installment item as paid</strong></p>
                        <div class="request-response">
                            <div class="code-section">
                                <div class="code-section-header">📤 Request</div>
                                <div class="code-section-body">
                                    <pre>POST /api/installment-item-pay/1
Authorization: Bearer {token}
Content-Type: application/json

{
  "paid_amount": 100.00,
  "reference": "PAYMENT-001"
}</pre>
                                </div>
                            </div>
                            <div class="code-section">
                                <div class="code-section-header">📥 Response (200 OK)</div>
                                <div class="code-section-body">
                                    <pre>{
  "success": true,
  "message": "Payment recorded successfully",
  "data": {
    "id": 1,
    "installment_id": 1,
    "amount": "100.00",
    "paid_amount": "100.00",
    "paid_at": "2024-01-15T14:30:00.000000Z",
    "reference": "PAYMENT-001",
    "status": "paid"
  }
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/installment-overdue</span>
                        </div>
                        <p><strong>Get overdue installment items</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": [
    {
      "id": 1,
      "installment_id": 1,
      "amount": "100.00",
      "due_date": "2024-01-10",
      "status": "overdue",
      "days_overdue": 5,
      "customer": {
        "id": 1,
        "name": "Jane Smith",
        "email": "jane@example.com"
      }
    }
  ]
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/installment-due-soon</span>
                        </div>
                        <p><strong>Get items due soon (within 7 days)</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": [
    {
      "id": 2,
      "installment_id": 1,
      "amount": "100.00",
      "due_date": "2024-01-22",
      "status": "pending",
      "days_remaining": 3,
      "customer": {
        "id": 1,
        "name": "Jane Smith",
        "email": "jane@example.com"
      }
    }
  ]
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/installment-stats/{id}</span>
                        </div>
                        <p><strong>Get statistics for a specific installment</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": {
    "installment_id": 1,
    "total_items": 12,
    "paid_items": 5,
    "pending_items": 7,
    "total_amount": "1200.00",
    "paid_amount": "500.00",
    "remaining_amount": "700.00",
    "completion_percentage": 41.67
  }
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/installment-all-stats</span>
                        </div>
                        <p><strong>Get statistics for all installments</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": {
    "total_installments": 20,
    "active_installments": 15,
    "total_amount": "50000.00",
    "paid_amount": "25000.00",
    "outstanding_amount": "25000.00",
    "completion_percentage": 50.00
  }
}</div>
                        </div>
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
                        <p><strong>Get comprehensive dashboard analytics</strong></p>
                        <div class="response-example">
                            <div class="response-label">Returns detailed analytics including:</div>
                            <ul class="features">
                                <li>Summary statistics (due soon, overdue, outstanding amounts)</li>
                                <li>Customer and installment counts</li>
                                <li>Monthly collection trends and growth</li>
                                <li>Detailed upcoming payments table</li>
                                <li>Overdue payments with customer contact info</li>
                                <li>Recent payments history</li>
                                <li>Top customers by outstanding amount</li>
                                <li>6-month collection trend data</li>
                            </ul>
                        </div>
                        <div class="code-block json">{
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
                            "upcoming": [...],
                            "overduePayments": [...],
                            "recentPayments": [...],
                            "topCustomers": [...],
                            "monthlyTrend": [...]
                            }
                            }</div>
                    </div>
                </section>

                <!-- Notifications Section -->
                <section id="notifications" class="section">
                    <h2>🔔 Notifications</h2>
                    <p>Manage in-app notifications and email notifications for payment reminders and updates.</p>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/notification-list</span>
                        </div>
                        <p><strong>List all notifications (paginated)</strong></p>
                        <div class="request-response">
                            <div class="code-section">
                                <div class="code-section-header">Request</div>
                                <div class="code-section-body">
                                    <pre>GET /api/notification-list
Authorization: Bearer {token}
Accept: application/json

Query Parameters:
  - page: Page number (optional)
  - per_page: Items per page (default: 15)
  - type: Filter by type (optional)
  - read: Filter by read status (true/false)</pre>
                                </div>
                            </div>
                            <div class="code-section">
                                <div class="code-section-header">Response</div>
                                <div class="code-section-body">
                                    <pre>{
  "success": true,
  "message": "Notifications retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "type": "overdue",
        "title": "Payment Overdue",
        "message": "Payment of $100.00 is 3 days overdue",
        "data": {...},
        "read_at": null,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "is_read": false
      }
    ],
    "per_page": 15,
    "total": 25
  }
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/notification-count</span>
                        </div>
                        <p><strong>Get unread notification count</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": {
    "unread_count": 5
  }
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/notification-mark-read/{id}</span>
                        </div>
                        <p><strong>Mark a notification as read</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "message": "Notification marked as read",
  "data": {
    "id": 1,
    "read_at": "2024-01-15T14:30:00.000000Z"
  }
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/notification-mark-all-read</span>
                        </div>
                        <p><strong>Mark all notifications as read</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "message": "All notifications marked as read",
  "data": {
    "updated_count": 5
  }
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/notification-generate</span>
                        </div>
                        <p><strong>Generate overdue and due soon notifications</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "message": "Notifications generated successfully",
  "data": {
    "overdue_notifications": 3,
    "due_soon_notifications": 5
  }
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/notification-send-emails</span>
                        </div>
                        <p><strong>Send email reminders for due soon and overdue payments</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "message": "Emails sent successfully",
  "data": {
    "due_soon_emails": 8,
    "overdue_emails": 3
  }
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method delete">DELETE</span>
                            <span class="endpoint-url">/api/notification-delete/{id}</span>
                        </div>
                        <p><strong>Delete a notification</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "message": "Notification deleted successfully"
}</div>
                        </div>
                    </div>

                    <div class="highlight">
                        <strong>📧 Email Notifications:</strong> The system automatically sends professional Arabic email templates for payment due reminders, overdue notices, payment confirmations, and installment creation notifications.
                    </div>
                </section>

                <!-- Users Section -->
                <section id="users" class="section">
                    <h2>Users (Owner Only)</h2>
                    <p class="highlight">These endpoints require owner role.</p>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/user-list</span>
                        </div>
                        <p><strong>List all users</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "user",
      "created_at": "2024-01-01T10:00:00.000000Z"
    }
  ]
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/user-create</span>
                        </div>
                        <p><strong>Create a new user (Owner only)</strong></p>
                        <div class="request-response">
                            <div class="code-section">
                                <div class="code-section-header">📤 Request</div>
                                <div class="code-section-body">
                                    <pre>POST /api/user-create
Authorization: Bearer {owner_token}
Content-Type: application/json

{
  "name": "New User",
  "email": "newuser@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "user"
}</pre>
                                </div>
                            </div>
                            <div class="code-section">
                                <div class="code-section-header">📥 Response (201 Created)</div>
                                <div class="code-section-body">
                                    <pre>{
  "success": true,
  "message": "User created successfully",
  "data": {
    "id": 2,
    "name": "New User",
    "email": "newuser@example.com",
    "role": "user",
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method get">GET</span>
                            <span class="endpoint-url">/api/user-show/{id}</span>
                        </div>
                        <p><strong>Get user details</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "user",
    "created_at": "2024-01-01T10:00:00.000000Z"
  }
}</div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method put">PUT</span>
                            <span class="endpoint-url">/api/user-update/{id}</span>
                        </div>
                        <p><strong>Update user</strong></p>
                        <div class="request-response">
                            <div class="code-section">
                                <div class="code-section-header">📤 Request</div>
                                <div class="code-section-body">
                                    <pre>PUT /api/user-update/1
Authorization: Bearer {owner_token}
Content-Type: application/json

{
  "name": "John Doe Updated",
  "role": "admin"
}</pre>
                                </div>
                            </div>
                            <div class="code-section">
                                <div class="code-section-header">📥 Response (200 OK)</div>
                                <div class="code-section-body">
                                    <pre>{
  "success": true,
  "message": "User updated successfully",
  "data": {
    "id": 1,
    "name": "John Doe Updated",
    "email": "john@example.com",
    "role": "admin",
    "updated_at": "2024-01-15T14:00:00.000000Z"
  }
}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="endpoint">
                        <div class="endpoint-header">
                            <span class="method delete">DELETE</span>
                            <span class="endpoint-url">/api/user-delete/{id}</span>
                        </div>
                        <p><strong>Delete user</strong></p>
                        <div class="response-example">
                            <div class="response-label">Response:</div>
                            <div class="code-block">{
  "success": true,
  "message": "User deleted successfully"
}</div>
                        </div>
                    </div>
                </section>

                <!-- Error Handling Section -->
                <section id="errors" class="section">
                    <h2>Error Handling</h2>
                    <p>All errors follow a consistent JSON format with standardized error codes:</p>
                    <div class="code-block json">{
                        "success": false,
                        "message": "Error message",
                        "error_code": "AUTH_001",
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

                    <h3>Error Code Categories</h3>
                    <p>The API uses a comprehensive error code system organized by functionality:</p>

                    <h4>Authentication & Authorization (AUTH_*)</h4>
                    <div class="code-block">AUTH_001 - Invalid Credentials
                        AUTH_002 - Token Expired
                        AUTH_003 - Token Invalid
                        AUTH_004 - Access Denied
                        AUTH_005 - Insufficient Permissions
                        AUTH_006 - Account Disabled
                        AUTH_007 - Email Not Verified</div>

                    <h4>Validation Errors (VAL_*)</h4>
                    <div class="code-block">VAL_001 - Validation Failed
                        VAL_002 - Required Field Missing
                        VAL_003 - Invalid Email Format
                        VAL_004 - Password Too Weak
                        VAL_005 - Invalid Date Format
                        VAL_006 - Invalid Numeric Value
                        VAL_007 - Value Out Of Range
                        VAL_008 - Duplicate Entry</div>

                    <h4>Customer Management (CUST_*)</h4>
                    <div class="code-block">CUST_001 - Customer Not Found
                        CUST_002 - Customer Already Exists
                        CUST_003 - Customer Update Failed
                        CUST_004 - Customer Delete Failed
                        CUST_005 - Customer Access Denied</div>

                    <h4>Installment Management (INST_*)</h4>
                    <div class="code-block">INST_001 - Installment Not Found
                        INST_002 - Installment Creation Failed
                        INST_003 - Installment Update Failed
                        INST_004 - Installment Delete Failed
                        INST_005 - Installment Access Denied
                        INST_006 - Invalid Installment Amount
                        INST_007 - Invalid Installment Period
                        INST_008 - Installment Already Completed
                        INST_009 - Installment Item Not Found
                        INST_010 - Installment Item Already Paid
                        INST_011 - Payment Amount Mismatch
                        INST_012 - Payment Date Invalid</div>

                    <h4>User Management (USER_*)</h4>
                    <div class="code-block">USER_001 - User Not Found
                        USER_002 - User Already Exists
                        USER_003 - User Creation Failed
                        USER_004 - User Update Failed
                        USER_005 - User Delete Failed
                        USER_006 - User Access Denied
                        USER_007 - Invalid User Role</div>

                    <h4>Database Errors (DB_*)</h4>
                    <div class="code-block">DB_001 - Database Connection Failed
                        DB_002 - Database Query Failed
                        DB_003 - Database Transaction Failed
                        DB_004 - Database Constraint Violation</div>

                    <h4>Business Logic (BIZ_*)</h4>
                    <div class="code-block">BIZ_001 - Business Rule Violation
                        BIZ_002 - Insufficient Funds
                        BIZ_003 - Operation Not Allowed
                        BIZ_004 - Resource In Use
                        BIZ_005 - Invalid Operation</div>

                    <h4>System Errors (SYS_*)</h4>
                    <div class="code-block">SYS_001 - System Maintenance
                        SYS_002 - Configuration Error
                        SYS_003 - Service Unavailable</div>

                    <h3>Error Response Examples</h3>

                    <h4>Authentication Error</h4>
                    <div class="code-block json">{
                        "success": false,
                        "message": "Invalid credentials",
                        "error_code": "AUTH_001",
                        "status_code": 401
                        }</div>

                    <h4>Validation Error</h4>
                    <div class="code-block json">{
                        "success": false,
                        "message": "Validation failed",
                        "error_code": "VAL_001",
                        "status_code": 422,
                        "errors": {
                        "email": ["The email field is required."],
                        "password": ["The password must be at least 8 characters."]
                        }
                        }</div>

                    <h4>Business Logic Error</h4>
                    <div class="code-block json">{
                        "success": false,
                        "message": "Installment item already paid",
                        "error_code": "INST_010",
                        "status_code": 400
                        }</div>
                </section>

                <!-- Frontend Integration Section -->
                <section id="frontend" class="section">
                    <h2>Frontend Integration</h2>
                    <p>Comprehensive guides for integrating with popular frontend frameworks and mobile platforms.</p>

                    <div class="frontend-section">
                        <div class="highlight">
                            <strong>🚀 Ready-to-use code examples</strong> with complete implementations for both web
                            and mobile development.
                        </div>

                        <h3>React Integration</h3>
                        <div class="endpoint">
                            <div class="endpoint-header">
                                <span class="method get">📚</span>
                                <span class="endpoint-url">Complete React Guide</span>
                            </div>
                            <p><strong>Full-featured React application with:</strong></p>
                            <ul class="features">
                                <li>Complete API service setup with Axios</li>
                                <li>Authentication context and hooks</li>
                                <li>Dashboard components with data tables</li>
                                <li>Customer and installment management</li>
                                <li>Error handling and loading states</li>
                                <li>Ant Design components</li>
                                <li>React Query for data fetching</li>
                                <li>Responsive design</li>
                            </ul>
                            @verbatim
                        <div class="code-block">// Example: Dashboard Stats Component
import React from 'react';
import { Card, Row, Col, Statistic } from 'antd';

const DashboardStats = ({ data }) => {
  const stats = [
    {
      title: 'Due Soon',
      value: data?.dueSoon || 0,
      icon: <CalendarOutlined />,
      color: '#1890ff',
    },
    // ... more stats
  ];

  return (
    <Row gutter={[16, 16]}>
      {stats.map((item, index) => (
        <Col xs={24} sm={12} lg={6} key={index}>
          <Card>
            <Statistic
              title={item.title}
              value={item.value}
              prefix={item.icon}
              valueStyle={{ color: item?.color }}
            />
          </Card>
        </Col>
      ))}
    </Row>
  );
};</div>
                        @endverbatim
                        </div>

                        <h3>Flutter Integration</h3>
                        <div class="endpoint">
                            <div class="endpoint-header">
                                <span class="method get">📱</span>
                                <span class="endpoint-url">Complete Flutter Guide</span>
                            </div>
                            <p><strong>Full-featured Flutter mobile app with:</strong></p>
                            <ul class="features">
                                <li>Complete API service with Dio</li>
                                <li>State management with Riverpod</li>
                                <li>Dashboard screens with data tables</li>
                                <li>Customer and installment management</li>
                                <li>Error handling and loading states</li>
                                <li>Material Design components</li>
                                <li>JSON serialization with code generation</li>
                                <li>Mobile-optimized UI</li>
                            </ul>
                            <div class="code-block">// Example: Dashboard Stats Card
                                class DashboardStatsCard extends StatelessWidget {
                                final String title;
                                final String value;
                                final IconData icon;
                                final Color color;

                                @override
                                Widget build(BuildContext context) {
                                return Card(
                                elevation: 4,
                                child: Padding(
                                padding: EdgeInsets.all(16.w),
                                child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                Row(
                                children: [
                                Icon(icon, color: color, size: 24.sp),
                                SizedBox(width: 8.w),
                                Expanded(
                                child: Text(
                                title,
                                style: TextStyle(
                                fontSize: 14.sp,
                                color: Colors.grey[600],
                                ),
                                ),
                                ),
                                ],
                                ),
                                SizedBox(height: 8.h),
                                Text(
                                value,
                                style: TextStyle(
                                fontSize: 24.sp,
                                fontWeight: FontWeight.bold,
                                color: color,
                                ),
                                ),
                                ],
                                ),
                                ),
                                );
                                }
                                }</div>
                        </div>

                        <h3>Key Features</h3>
                        <div class="solid-principles">
                            <div class="principle-card">
                                <h4>📊 Rich Dashboard Data</h4>
                                <p>Comprehensive analytics with summary stats, detailed tables, and trend data for
                                    complete business insights.</p>
                            </div>
                            <div class="principle-card">
                                <h4>🔐 Complete Authentication</h4>
                                <p>Full auth flow with login, register, logout, token management, and role-based access
                                    control.</p>
                            </div>
                            <div class="principle-card">
                                <h4>📱 Mobile Ready</h4>
                                <p>Responsive design and mobile-optimized components for both web and mobile
                                    applications.</p>
                            </div>
                            <div class="principle-card">
                                <h4>⚡ Production Ready</h4>
                                <p>Error handling, loading states, form validation, and best practices for production
                                    deployment.</p>
                            </div>
                        </div>

                        <h3>Quick Start</h3>
                        <div class="code-block"># React Setup
                            npx create-react-app installment-manager
                            cd installment-manager
                            npm install axios react-query @tanstack/react-query antd

                            # Flutter Setup
                            flutter create installment_manager_app
                            cd installment_manager_app
                            flutter pub add dio riverpod flutter_riverpod shared_preferences</div>

                        <div class="highlight">
                            <strong>📖 Documentation:</strong> See <code>FRONTEND_INTEGRATION_REACT.md</code> and
                            <code>FRONTEND_INTEGRATION_FLUTTER.md</code> for complete implementation guides with
                            copy-paste ready code.
                        </div>
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
