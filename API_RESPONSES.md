# 📄 API Response Reference (Arabic Localization)

هذا المستند يلخص أهم نقاط الـ API والبيانات التي تعيدها الواجهة الخلفية بعد تحديثات نظام الاشتراكات والحدود. كل الردود تأتي بالصيغة العامة:

```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": { ... }
}
```

وتكون الرسائل دائمًا بالعربية (سواء نجاح أو خطأ).

---

## 🔐 Auth Endpoints

### `POST /api/auth/login`

#### Request Body

```json
{
    "email": "owner@example.com",
    "password": "secret"
}
```

### `POST /api/auth/register`

#### Request Body

```json
{
    "name": "Test User",
    "email": "user@example.com",
    "password": "secret",
    "password_confirmation": "secret",
    "subscription_id": 1
}
```

### `GET /api/auth/me`

لا يتطلب جسم؛ فقط توكن المصادقة في الهيدر.

#### Response

```json
{
    "success": true,
    "message": "تم تسجيل الدخول بنجاح",
    "data": {
        "user": {
            "id": 1,
            "name": "Test User",
            "email": "test@example.com",
            "role": "user",
            "created_at": "2025-01-01T10:20:30.000000Z",
            "updated_at": "2025-01-01T10:20:30.000000Z",
            "user_limit": {
                "id": 10,
                "user_id": 1,
                "subscription_name": "الخطة الأساسية",
                "subscription_slug": "basic-plan",
                "currency": "EGP",
                "price": 199.99,
                "duration": "monthly",
                "description": "وصف الخطة...",
                "start_date": "2025-01-01",
                "end_date": "2025-02-01",
                "status": "active",
                "limits": {
                    "customers": { "from": 0, "to": 100 },
                    "installments": { "from": 0, "to": 200 },
                    "notifications": { "from": 0, "to": 1000 },
                    "features": { "advanced_reports": true },
                    "reports": true
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
                },
                "created_at": "2025-01-01T10:20:30.000000Z",
                "updated_at": "2025-01-01T10:20:30.000000Z"
            },
            "current_subscription": {
                "name": "الخطة الأساسية",
                "slug": "basic-plan",
                "status": "active",
                "start_date": "2025-01-01",
                "end_date": "2025-02-01",
                "currency": "EGP",
                "price": 199.99,
                "duration": "monthly"
            }
        },
        "token": "SANCTUM_TOKEN",
        "token_type": "Bearer"
    }
}
```

> في حالة الخطأ (مثلاً بيانات غير صحيحة) تكون الرسالة `بيانات الاعتماد غير صحيحة` مع `success: false`.

---

## 📦 Subscription Plans

### `GET /api/subscriptions-public` (عام)

#### Request

-   GET بدون جسم.

#### Response

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
            "features": { "basic_reports": true },
            "created_at": "2025-01-01T10:20:30.000000Z",
            "updated_at": "2025-01-01T10:20:30.000000Z"
        }
    ]
}
```

### (مالك) `GET /api/subscriptions-admin`

#### Request Parameters

-   `per_page` اختياري (افتراضي 15)

#### Response

```json
{
  "success": true,
  "message": "تم جلب خطط الاشتراك بنجاح",
  "data": {
    "data": [...],
    "links": {...},
    "meta": {...}
  }
}
```

### (مالك) `POST /api/subscriptions-create`

#### Request Body

```json
{
    "name": "الخطة الذهبية",
    "slug": "gold-plan",
    "currency": "EGP",
    "price": 499.99,
    "duration": "monthly",
    "description": "وصف الخطة...",
    "customers": { "from": 0, "to": 200 },
    "installments": { "from": 0, "to": 500 },
    "notifications": { "from": 0, "to": 5000 },
    "reports": true,
    "features": { "priority_support": true },
    "is_active": true
}
```

### (مالك) `GET /api/subscriptions-show/{subscription}`

يعيد تفاصيل الخطة المحددة.

### (مالك) `PUT /api/subscriptions-update/{subscription}`

#### Request Body

-   نفس حقول الإنشاء لكن كلها اختيارية.

### (مالك) `DELETE /api/subscriptions-delete/{subscription}`

#### Response

```json
{
    "success": true,
    "message": "تم حذف خطة الاشتراك بنجاح"
}
```

### (مالك) `POST /api/subscriptions/{subscription}/assign`

#### Request Body

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

## 📊 User Limits

### `GET /api/limits/current`

#### Request

-   GET بدون جسم.
-   يتطلب التوكن.

### `GET /api/limits/can-create/{resource}`

#### Request

-   `{resource}` = `customers | installments | notifications`

### `POST /api/limits/increment/{resource}`

#### Request Body

```json
{
    "count": 1
}
```

### `POST /api/limits/decrement/{resource}`

#### Request Body

```json
{
    "count": 1
}
```

### `GET /api/limits/feature/{feature}`

#### Request

-   `{feature}` مثال: `advanced_reports`

---

## 🔔 Notifications

### `GET /api/notification-list`

#### Request Parameters

-   `unread_only` اختياري (true/false)

### `GET /api/notification-count`

#### Request

-   GET بدون جسم.

### `POST /api/notification-mark-read/{id}`

#### Request

-   POST بدون جسم.
-   `{id}` هو معرف الإشعار.

### `POST /api/notification-delete/{id}`

#### Response

```json
{
    "success": true,
    "message": "تم حذف الإشعار بنجاح"
}
```

---

## 👥 Customers / 💰 Installments

أمثلة مختصرة نظرًا لعدم تغيّر البنية:

-   `GET /api/customer-list`

    ```json
    {
      "success": true,
      "message": "تم جلب العملاء بنجاح",
      "data": {
        "data": [
          {
            "id": 12,
            "name": "...",
            "email": "...",
            "phone": "...",
            ...
          }
        ],
        "links": { ... },
        "meta": { ... }
      }
    }
    ```

-   `POST /api/customer-create`

    #### Request Body

    ```json
    {
        "name": "عميل جديد",
        "email": "client@example.com",
        "phone": "+201000000000",
        "address": "القاهرة",
        "notes": "ملاحظات"
    }
    ```

    يرفض الطلب برسالة: `"لقد وصلت إلى الحد الأقصى لعدد العملاء المسموح به في خطتك."` إذا تجاوز المستخدم الحد.

-   `GET /api/customer-show/{id}` → `message: "تم جلب العميل بنجاح"`
-   `PUT /api/customer-update/{id}` → `message: "تم تحديث العميل بنجاح"`
-   `DELETE /api/customer-delete/{id}` → `message: "تم حذف العميل بنجاح"`
-   `GET /api/customer-stats/{id}`

الشيء نفسه مع الأقساط:

-   `GET /api/installment-list` → `"تم جلب الأقساط بنجاح"`
-   `POST /api/installment-create`

    #### Request Body

    ```json
    {
        "customer_id": 12,
        "total_amount": 10000,
        "products": ["Product A", "Product B"],
        "start_date": "2025-01-01",
        "months": 12,
        "notes": "ملاحظات"
    }
    ```

    يرفض الطلب برسالة: `"لقد وصلت إلى الحد الأقصى لعدد الأقساط المسموح بها في خطتك."` إذا تجاوز الحد.

-   `GET /api/installment-show/{id}` → `"تم جلب القسط بنجاح"`
-   `GET /api/installment-overdue`
-   `GET /api/installment-due-soon`
-   `GET /api/installment-stats/{id}`
-   `GET /api/installment-all-stats`
-   `POST /api/installment-item-pay/{item}`
-   ... إلخ.

---

## ⚠️ رسائل الخطأ الأساسية

-   `غير مصرح` → عند عدم تسجيل الدخول.
-   `ممنوع الوصول` → عند محاولة الوصول لبيانات غير مسموح بها.
-   `المورد غير موجود` → إذا لم يتم العثور على العنصر المطلوب.
-   `تعذّر زيادة الاستهلاك للمورد المحدد` / `تعذّر تقليل الاستهلاك للمورد المحدد`.
-   `لقد وصلت إلى الحد الأقصى ...` (تخص كل مورد على حدة).

---

## 📌 ملاحظات عامة

-   كل الردود JSON فقط، لا يوجد HTML.
-   الحقول الزمنية تأتي بصيغة ISO 8601.
-   القيم المرقمية (سعر، حدود، استخدام) تلتزم بنوعها (مفاتيح `from/to`, `usage`, `remaining`).
-   عند التعامل مع الـ frontend يُنصح بعرض الرسائل العربية كما هي.
-   تأكد من إرسال التوكن في العناوين (`Authorization: Bearer ...`) للروابط المحمية.

> تم إعداد هذا المرجع ليتماشى مع التحديثات الأخيرة حول الاشتراكات وحدود المستخدمين والرسائل المعربة. استخدمه كمرجع سريع لفريق الواجهة الأمامية. بالتوفيق! 🎉
