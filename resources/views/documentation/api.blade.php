<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>توثيق API - Installment Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f7fb;
            --card: #ffffff;
            --primary: #4c6ef5;
            --primary-dark: #364fc7;
            --text: #1f2933;
            --muted: #52606d;
            --border: #e4e7eb;
            --pill-bg: #edf2ff;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 20px 80px;
        }

        header {
            text-align: center;
            margin-bottom: 48px;
            padding: 32px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 16px;
            color: white;
        }

        header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 12px;
        }

        header p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .auth-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 0.85rem;
            margin-top: 16px;
            font-weight: 600;
        }

        .auth-badge.public {
            background: rgba(16, 185, 129, 0.2);
        }

        .auth-badge.protected {
            background: rgba(239, 68, 68, 0.2);
        }

        .auth-badge.owner {
            background: rgba(245, 158, 11, 0.2);
        }

        section {
            margin-bottom: 32px;
        }

        .card {
            background: var(--card);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 28px;
            margin-bottom: 24px;
        }

        h2 {
            font-size: 1.75rem;
            margin-bottom: 20px;
            color: var(--primary-dark);
            border-bottom: 2px solid var(--border);
            padding-bottom: 12px;
        }

        h3 {
            font-size: 1.3rem;
            margin-top: 24px;
            margin-bottom: 16px;
            color: var(--primary);
        }

        .endpoint-item {
            margin-bottom: 32px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 12px;
            border-right: 4px solid var(--primary);
        }

        .endpoint-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .method {
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .method.get {
            background: #dbeafe;
            color: #1e40af;
        }

        .method.post {
            background: #d1fae5;
            color: #065f46;
        }

        .method.put {
            background: #fef3c7;
            color: #92400e;
        }

        .method.delete {
            background: #fee2e2;
            color: #991b1b;
        }

        .endpoint-path {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text);
        }

        .endpoint-description {
            color: var(--muted);
            margin-bottom: 16px;
            font-size: 0.95rem;
        }

        .request-section,
        .response-section {
            margin-top: 20px;
            padding: 16px;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .request-section h4,
        .response-section h4 {
            font-size: 1rem;
            margin-bottom: 12px;
            color: var(--primary-dark);
        }

        pre {
            background: #1e293b;
            color: #e2e8f0;
            padding: 16px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.85rem;
            line-height: 1.5;
            direction: ltr;
            text-align: left;
        }

        code {
            font-family: 'Courier New', monospace;
        }

        .field-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .field-table th,
        .field-table td {
            padding: 10px;
            text-align: right;
            border-bottom: 1px solid var(--border);
        }

        .field-table th {
            background: #f3f4f6;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .required {
            color: var(--error);
            font-weight: 600;
        }

        .optional {
            color: var(--muted);
        }

        .note {
            margin-top: 16px;
            padding: 14px;
            border-right: 3px solid var(--primary);
            background: #eef2ff;
            border-radius: 8px;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .toc {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            border: 1px solid var(--border);
        }

        .toc h2 {
            font-size: 1.5rem;
            margin-bottom: 16px;
            border: none;
            padding: 0;
        }

        .toc ul {
            list-style: none;
            margin: 0;
        }

        .toc li {
            margin: 8px 0;
        }

        .toc a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .toc a:hover {
            text-decoration: underline;
        }

        footer {
            text-align: center;
            color: var(--muted);
            margin-top: 48px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1>📚 توثيق API الكامل</h1>
            <p>دليل شامل لجميع نقاط النهاية (Endpoints) مع أمثلة الطلبات والاستجابات</p>
            <div class="auth-badge public">🌐 API Base: /api</div>
        </header>

        <div class="toc">
            <h2>📑 جدول المحتويات</h2>
            <ul>
                <li><a href="#auth">🔐 المصادقة (Authentication)</a></li>
                <li><a href="#subscriptions">📦 خطط الاشتراك (Subscriptions)</a></li>
                <li><a href="#limits">📊 حدود المستخدم (User Limits)</a></li>
                <li><a href="#customers">👥 العملاء (Customers)</a></li>
                <li><a href="#installments">💰 الأقساط (Installments)</a></li>
                <li><a href="#notifications">🔔 الإشعارات (Notifications)</a></li>
                <li><a href="#users">👤 المستخدمين (Users) - للمالكين فقط</a></li>
                <li><a href="#dashboard">📊 لوحة التحكم (Dashboard)</a></li>
            </ul>
        </div>

        <!-- Authentication Section -->
        <section id="auth">
            <div class="card">
                <h2>🔐 المصادقة (Authentication)</h2>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/auth/register</span>
                        <span class="auth-badge public">عام (Public)</span>
                    </div>
                    <div class="endpoint-description">
                        تسجيل مستخدم جديد. يمكن اختيار خطة اشتراك عند التسجيل.
                    </div>
                    <div class="request-section">
                        <h4>Request Body:</h4>
                        <pre><code>{
  "name": "أحمد محمد",
  "email": "ahmed@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "subscription_id": 1  // اختياري
}</code></pre>
                        <table class="field-table">
                            <tr>
                                <th>الحقل</th>
                                <th>النوع</th>
                                <th>مطلوب</th>
                                <th>الوصف</th>
                            </tr>
                            <tr>
                                <td>name</td>
                                <td>string</td>
                                <td class="required">نعم</td>
                                <td>اسم المستخدم</td>
                            </tr>
                            <tr>
                                <td>email</td>
                                <td>email</td>
                                <td class="required">نعم</td>
                                <td>البريد الإلكتروني (يجب أن يكون فريداً)</td>
                            </tr>
                            <tr>
                                <td>password</td>
                                <td>string</td>
                                <td class="required">نعم</td>
                                <td>كلمة المرور</td>
                            </tr>
                            <tr>
                                <td>password_confirmation</td>
                                <td>string</td>
                                <td class="required">نعم</td>
                                <td>تأكيد كلمة المرور</td>
                            </tr>
                            <tr>
                                <td>subscription_id</td>
                                <td>integer</td>
                                <td class="optional">لا</td>
                                <td>معرف خطة الاشتراك (إذا لم يتم تحديده، سيتم تعيين الخطة المجانية)</td>
                            </tr>
                        </table>
                    </div>
                    <div class="response-section">
                        <h4>Response (200 OK):</h4>
                        <pre><code>{
  "success": true,
  "message": "تم التسجيل بنجاح",
  "data": {
    "user": {
      "id": 1,
      "name": "أحمد محمد",
      "email": "ahmed@example.com",
      "role": "user",
      "user_limit": { ... }
    },
    "token": "1|xxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}</code></pre>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/auth/login</span>
                        <span class="auth-badge public">عام (Public)</span>
                    </div>
                    <div class="endpoint-description">
                        تسجيل الدخول والحصول على توكن المصادقة.
                    </div>
                    <div class="request-section">
                        <h4>Request Body:</h4>
                        <pre><code>{
  "email": "ahmed@example.com",
  "password": "password123"
}</code></pre>
                    </div>
                    <div class="response-section">
                        <h4>Response (200 OK):</h4>
                        <pre><code>{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح",
  "data": {
    "user": {
      "id": 1,
      "name": "أحمد محمد",
      "email": "ahmed@example.com",
      "role": "user",
      "user_limit": {
        "subscription_name": "الخطة الأساسية",
        "limits": { "customers": { "from": 0, "to": 100 }, ... },
        "usage": { "customers_used": 10, ... },
        "remaining": { "customers": 90, ... }
      }
    },
    "token": "1|xxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}</code></pre>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/auth/me</span>
                        <span class="auth-badge protected">محمي (Protected)</span>
                    </div>
                    <div class="endpoint-description">
                        الحصول على بيانات المستخدم الحالي مع حدود الاشتراك.
                    </div>
                    <div class="request-section">
                        <h4>Headers:</h4>
                        <pre><code>Authorization: Bearer {token}</code></pre>
                    </div>
                    <div class="response-section">
                        <h4>Response (200 OK):</h4>
                        <pre><code>{
  "success": true,
  "message": "تم جلب البيانات بنجاح",
  "data": {
    "user": { ... }
  }
}</code></pre>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/auth/logout</span>
                        <span class="auth-badge protected">محمي (Protected)</span>
                    </div>
                    <div class="endpoint-description">
                        تسجيل الخروج وحذف التوكن.
                    </div>
                    <div class="response-section">
                        <h4>Response (200 OK):</h4>
                        <pre><code>{
  "success": true,
  "message": "تم تسجيل الخروج بنجاح"
}</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- Subscriptions Section -->
        <section id="subscriptions">
            <div class="card">
                <h2>📦 خطط الاشتراك (Subscriptions)</h2>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/subscriptions-public</span>
                        <span class="auth-badge public">عام (Public)</span>
                    </div>
                    <div class="endpoint-description">
                        الحصول على قائمة بجميع خطط الاشتراك النشطة المتاحة للجمهور.
                    </div>
                    <div class="response-section">
                        <h4>Response (200 OK):</h4>
                        <pre><code>{
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
}</code></pre>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/subscriptions/{subscription}/change</span>
                        <span class="auth-badge protected">محمي (Protected)</span>
                    </div>
                    <div class="endpoint-description">
                        تغيير اشتراك المستخدم الحالي (ترقية أو تخفيض). يحافظ على الاستخدام الحالي.
                    </div>
                    <div class="request-section">
                        <h4>Request Body (جميع الحقول اختيارية):</h4>
                        <pre><code>{
  "start_date": "2025-01-01",
  "end_date": "2025-02-01",
  "status": "active",
  "features": { "custom": true }
}</code></pre>
                    </div>
                    <div class="response-section">
                        <h4>Response (200 OK):</h4>
                        <pre><code>{
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
}</code></pre>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/subscriptions-admin</span>
                        <span class="auth-badge owner">مالك فقط (Owner)</span>
                    </div>
                    <div class="endpoint-description">
                        قائمة بجميع خطط الاشتراك (نشطة وغير نشطة) مع pagination.
                    </div>
                    <div class="request-section">
                        <h4>Query Parameters:</h4>
                        <pre><code>?per_page=15  // اختياري، افتراضي 15</code></pre>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/subscriptions-create</span>
                        <span class="auth-badge owner">مالك فقط (Owner)</span>
                    </div>
                    <div class="endpoint-description">
                        إنشاء خطة اشتراك جديدة.
                    </div>
                    <div class="request-section">
                        <h4>Request Body:</h4>
                        <pre><code>{
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
}</code></pre>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/subscriptions/{subscription}/assign</span>
                        <span class="auth-badge owner">مالك فقط (Owner)</span>
                    </div>
                    <div class="endpoint-description">
                        تعيين خطة اشتراك لمستخدم محدد.
                    </div>
                    <div class="request-section">
                        <h4>Request Body:</h4>
                        <pre><code>{
  "user_id": 12,
  "start_date": "2025-01-01",
  "end_date": "2025-02-01",
  "status": "active",
  "features": { "custom": true }
}</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- User Limits Section -->
        <section id="limits">
            <div class="card">
                <h2>📊 حدود المستخدم (User Limits)</h2>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/limits/current</span>
                        <span class="auth-badge protected">محمي (Protected)</span>
                    </div>
                    <div class="endpoint-description">
                        الحصول على الحدود الحالية والاستخدام للمستخدم المسجل.
                    </div>
                    <div class="response-section">
                        <h4>Response (200 OK):</h4>
                        <pre><code>{
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
}</code></pre>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/limits/can-create/{resourceType}</span>
                        <span class="auth-badge protected">محمي (Protected)</span>
                    </div>
                    <div class="endpoint-description">
                        التحقق من إمكانية إنشاء مورد معين. القيم: customers, installments, notifications
                    </div>
                    <div class="response-section">
                        <h4>Response (200 OK):</h4>
                        <pre><code>{
  "success": true,
  "message": "يمكن إنشاء المورد",
  "data": {
    "can_create": true,
    "remaining": 88
  }
}</code></pre>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/limits/refresh</span>
                        <span class="auth-badge protected">محمي (Protected)</span>
                    </div>
                    <div class="endpoint-description">
                        إعادة حساب الاستخدام من قاعدة البيانات.
                    </div>
                </div>
            </div>
        </section>

        <!-- Customers Section -->
        <section id="customers">
            <div class="card">
                <h2>👥 العملاء (Customers)</h2>
                <div class="note">
                    ⚠️ جميع endpoints العملاء تتطلب اشتراك نشط (EnsureActiveSubscription middleware)
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/customer-list</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        قائمة بجميع عملاء المستخدم مع pagination.
                    </div>
                    <div class="response-section">
                        <h4>Response (200 OK):</h4>
                        <pre><code>{
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
}</code></pre>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/customer-create</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        إنشاء عميل جديد. يتحقق من الحد الأقصى للعملاء.
                    </div>
                    <div class="request-section">
                        <h4>Request Body:</h4>
                        <pre><code>{
  "name": "محمد أحمد",
  "email": "mohamed@example.com",
  "phone": "+201000000000",
  "address": "القاهرة",
  "notes": "ملاحظات"
}</code></pre>
                        <table class="field-table">
                            <tr>
                                <th>الحقل</th>
                                <th>النوع</th>
                                <th>مطلوب</th>
                            </tr>
                            <tr>
                                <td>name</td>
                                <td>string</td>
                                <td class="required">نعم</td>
                            </tr>
                            <tr>
                                <td>email</td>
                                <td>email</td>
                                <td class="optional">لا</td>
                            </tr>
                            <tr>
                                <td>phone</td>
                                <td>string</td>
                                <td class="optional">لا</td>
                            </tr>
                            <tr>
                                <td>address</td>
                                <td>string</td>
                                <td class="optional">لا</td>
                            </tr>
                            <tr>
                                <td>notes</td>
                                <td>string</td>
                                <td class="optional">لا</td>
                            </tr>
                        </table>
                    </div>
                    <div class="note">
                        ⚠️ إذا تجاوز المستخدم الحد الأقصى، سيرجع الخطأ: "لقد وصلت إلى الحد الأقصى لعدد العملاء المسموح
                        به في خطتك."
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/customer-show/{id}</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        الحصول على تفاصيل عميل معين مع أقساطه.
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method put">PUT</span>
                        <span class="endpoint-path">/api/customer-update/{id}</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        تحديث بيانات عميل.
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method delete">DELETE</span>
                        <span class="endpoint-path">/api/customer-delete/{id}</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        حذف عميل. سيتم تقليل عداد الاستخدام تلقائياً.
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/customer-stats/{id}</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        إحصائيات عميل معين (إجمالي الأقساط، المدفوع، المتبقي).
                    </div>
                </div>
            </div>
        </section>

        <!-- Installments Section -->
        <section id="installments">
            <div class="card">
                <h2>💰 الأقساط (Installments)</h2>
                <div class="note">
                    ⚠️ جميع endpoints الأقساط تتطلب اشتراك نشط
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/installment-list</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        قائمة بجميع أقساط المستخدم.
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/installment-create</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        إنشاء خطة أقساط جديدة. يتحقق من الحد الأقصى للأقساط.
                    </div>
                    <div class="request-section">
                        <h4>Request Body:</h4>
                        <pre><code>{
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
}</code></pre>
                        <table class="field-table">
                            <tr>
                                <th>الحقل</th>
                                <th>النوع</th>
                                <th>مطلوب</th>
                            </tr>
                            <tr>
                                <td>customer_id</td>
                                <td>integer</td>
                                <td class="required">نعم</td>
                            </tr>
                            <tr>
                                <td>total_amount</td>
                                <td>numeric</td>
                                <td class="required">نعم</td>
                            </tr>
                            <tr>
                                <td>months</td>
                                <td>integer</td>
                                <td class="required">نعم (1-120)</td>
                            </tr>
                            <tr>
                                <td>start_date</td>
                                <td>date</td>
                                <td class="required">نعم</td>
                            </tr>
                            <tr>
                                <td>products</td>
                                <td>array</td>
                                <td class="required">نعم (حد أدنى عنصر واحد)</td>
                            </tr>
                            <tr>
                                <td>products[].name</td>
                                <td>string</td>
                                <td class="required">نعم</td>
                            </tr>
                            <tr>
                                <td>products[].qty</td>
                                <td>integer</td>
                                <td class="required">نعم</td>
                            </tr>
                            <tr>
                                <td>products[].price</td>
                                <td>numeric</td>
                                <td class="required">نعم</td>
                            </tr>
                            <tr>
                                <td>notes</td>
                                <td>string</td>
                                <td class="optional">لا</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/installment-overdue</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        قائمة بالأقساط المتأخرة (overdue).
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/installment-due-soon</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        قائمة بالأقساط المستحقة قريباً (خلال 7 أيام).
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/installment-item-pay/{item}</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        تسجيل دفعة لقسط معين.
                    </div>
                    <div class="request-section">
                        <h4>Request Body:</h4>
                        <pre><code>{
  "paid_amount": 500,
  "reference": "رقم المرجع"
}</code></pre>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/installment-all-stats</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        إحصائيات شاملة لجميع الأقساط.
                    </div>
                </div>
            </div>
        </section>

        <!-- Notifications Section -->
        <section id="notifications">
            <div class="card">
                <h2>🔔 الإشعارات (Notifications)</h2>
                <div class="note">
                    ⚠️ جميع endpoints الإشعارات تتطلب اشتراك نشط
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/notification-list</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        قائمة بجميع إشعارات المستخدم.
                    </div>
                    <div class="request-section">
                        <h4>Query Parameters:</h4>
                        <pre><code>?unread_only=true  // اختياري</code></pre>
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/notification-count</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        عدد الإشعارات غير المقروءة.
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/notification-mark-read/{id}</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        تحديد إشعار كمقروء.
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/notification-mark-all-read</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        تحديد جميع الإشعارات كمقروءة.
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/notification-generate</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        إنشاء إشعارات تذكير للأقساط المستحقة. يتحقق من الحد الأقصى للإشعارات.
                    </div>
                </div>
            </div>
        </section>

        <!-- Dashboard Section -->
        <section id="dashboard">
            <div class="card">
                <h2>📊 لوحة التحكم (Dashboard)</h2>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/dashboard</span>
                        <span class="auth-badge protected">محمي + اشتراك</span>
                    </div>
                    <div class="endpoint-description">
                        إحصائيات شاملة للوحة التحكم (الأقساط المتأخرة، المستحقة قريباً، المبالغ المستحقة).
                    </div>
                    <div class="response-section">
                        <h4>Response (200 OK):</h4>
                        <pre><code>{
  "success": true,
  "message": "تم جلب البيانات بنجاح",
  "data": {
    "overdue_count": 5,
    "due_soon_count": 10,
    "total_outstanding": 50000,
    "upcoming_payments": [ ... ]
  }
}</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- Users Section (Owner Only) -->
        <section id="users">
            <div class="card">
                <h2>👤 المستخدمين (Users) - للمالكين فقط</h2>
                <div class="note">
                    ⚠️ جميع endpoints المستخدمين متاحة فقط للمالكين (Owner role)
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method get">GET</span>
                        <span class="endpoint-path">/api/user-list</span>
                        <span class="auth-badge owner">مالك فقط</span>
                    </div>
                    <div class="endpoint-description">
                        قائمة بجميع المستخدمين مع pagination.
                    </div>
                </div>

                <div class="endpoint-item">
                    <div class="endpoint-header">
                        <span class="method post">POST</span>
                        <span class="endpoint-path">/api/user-create</span>
                        <span class="auth-badge owner">مالك فقط</span>
                    </div>
                    <div class="endpoint-description">
                        إنشاء مستخدم جديد.
                    </div>
                    <div class="request-section">
                        <h4>Request Body:</h4>
                        <pre><code>{
  "name": "مستخدم جديد",
  "email": "user@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "user"
}</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <footer>
            <p><strong>ملاحظات مهمة:</strong></p>
            <ul style="list-style: none; margin-top: 16px;">
                <li>✅ جميع الرسائل بالعربية</li>
                <li>✅ جميع الردود بصيغة JSON موحدة: { "success": true/false, "message": "...", "data": {...} }</li>
                <li>✅ التوكن يُرسل في Header: <code>Authorization: Bearer {token}</code></li>
                <li>✅ عند تجاوز الحدود، يتم إرجاع رسالة خطأ بالعربية مع كود 403</li>
                <li>✅ المالكون (Owners) لديهم صلاحيات غير محدودة</li>
            </ul>
        </footer>
    </div>
</body>

</html>
