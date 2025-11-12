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

### `POST /api/auth/register`

### `GET /api/auth/me`

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

### `GET /api/subscriptions` (عام)

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
    },
    ...
  ]
}
```

### (مالك) `GET /api/subscriptions/admin`

نفس البنية لكن داخل `data` كائن Pagination من Laravel مع `data`, `links`, `meta`.

### (مالك) `POST /api/subscriptions`

```json
{
  "success": true,
  "message": "تم إنشاء خطة الاشتراك بنجاح",
  "data": {
    "id": 5,
    "name": "...",
    ...
  }
}
```

### (مالك) `POST /api/subscriptions/{id}/assign`

يعيد `UserLimitResource` بالكامل بنفس الشكل الموضح في قسم Auth فوق.

---

## 📊 User Limits

### `GET /api/limits/current`

```json
{
    "success": true,
    "message": "تم جلب الحدود الحالية بنجاح",
    "data": {
        "subscription": {
            "name": "الخطة الأساسية",
            "slug": "basic-plan",
            "price": 199.99,
            "currency": "EGP",
            "duration": "monthly",
            "description": "وصف الخطة...",
            "start_date": "2025-01-01",
            "end_date": "2025-02-01",
            "status": "active"
        },
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
        }
    }
}
```

إذا لم توجد حدود لسبب ما:

```json
{
    "success": false,
    "message": "لا توجد حدود مضبوطة لهذا المستخدم"
}
```

### `GET /api/limits/can-create/{resource}`

```json
{
    "success": true,
    "message": "يمكنك إنشاء موارد إضافية.",
    "data": {
        "resource_type": "customers",
        "can_create": true,
        "remaining": 88,
        "subscription": {
            "name": "...",
            "slug": "...",
            "status": "active",
            "start_date": "...",
            "end_date": "...",
            "currency": "EGP",
            "price": 199.99,
            "duration": "monthly"
        },
        "message": "يمكنك إنشاء موارد إضافية."
    }
}
```

لو تجاوز الحد:

```json
{
  "success": true,
  "message": "لقد وصلت إلى الحد الأقصى لعدد العملاء المسموح به في خطتك.",
  "data": {
    "resource_type": "customers",
    "can_create": false,
    "remaining": 0,
    "subscription": { ... نفس الحقل ... },
    "message": "لقد وصلت إلى الحد الأقصى لعدد العملاء المسموح به في خطتك."
  }
}
```

### `POST /api/limits/increment/{resource}`

```json
{
  "success": true,
  "message": "تمت زيادة استهلاك المورد بنجاح",
  "data": {
    "resource_type": "customers",
    "incremented_by": 1,
    "remaining": 87,
    "subscription": { ... التفاصيل ... }
  }
}
```

عند الفشل (مثلاً تخطي الحد):

```json
{
    "success": false,
    "message": "تعذّر زيادة الاستهلاك للمورد المحدد"
}
```

### `POST /api/limits/decrement/{resource}`

```json
{
  "success": true,
  "message": "تم تقليل استهلاك المورد بنجاح",
  "data": {
    "resource_type": "customers",
    "decremented_by": 1,
    "remaining": 88,
    "subscription": { ... التفاصيل ... }
  }
}
```

### `GET /api/limits/feature/{feature}`

```json
{
  "success": true,
  "message": "تم التحقق من إمكانية الوصول إلى الميزة بنجاح",
  "data": {
    "feature": "advanced_reports",
    "can_access": true,
    "subscription": { ... التفاصيل ... }
  }
}
```

---

## 🔔 Notifications

### `GET /api/notification-list`

```json
{
  "success": true,
  "message": "تم جلب الإشعارات بنجاح",
  "data": [
    {
      "id": 200,
      "title": "...",
      "message": "...",
      "type": "overdue",
      "read_at": null,
      "created_at": "2025-01-01T10:20:30.000000Z"
    },
    ...
  ]
}
```

### `GET /api/notification-count`

```json
{
    "success": true,
    "message": "تم جلب عدد الإشعارات غير المقروءة بنجاح",
    "data": {
        "count": 5
    }
}
```

### `POST /api/notification-mark-read/{id}`

نجاح:

```json
{
    "success": true,
    "message": "تم وضع علامة مقروء على الإشعار",
    "data": { "marked": true }
}
```

مقروء مسبقًا:

```json
{
    "success": true,
    "message": "الإشعار مقروء مسبقاً",
    "data": { "marked": false }
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

-   `POST /api/customer-create` → `message: "تم إنشاء العميل بنجاح"`
-   `GET /api/customer-show/{id}` → `message: "تم جلب العميل بنجاح"`
-   `PUT /api/customer-update/{id}` → `message: "تم تحديث العميل بنجاح"`
-   `DELETE /api/customer-delete/{id}` → `message: "تم حذف العميل بنجاح"`

الشيء نفسه مع الأقساط:

-   `GET /api/installment-list` → `"تم جلب الأقساط بنجاح"`
-   `POST /api/installment-create` → `"تم إنشاء القسط بنجاح"`
-   `GET /api/installment-show/{id}` → `"تم جلب القسط بنجاح"`
-   `POST /api/installment-item-pay/{item}` → `"تم تسجيل الدفعة بنجاح"`
-   `GET /api/installment-due-soon` → `"تم جلب الأقساط المستحقة قريباً بنجاح"`
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
