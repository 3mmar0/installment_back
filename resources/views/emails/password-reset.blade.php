<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور - {{ config('app.name') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Tahoma', 'Arial', sans-serif;
            line-height: 1.8;
            color: #333;
            background-color: #f4f6f8;
            direction: rtl;
            text-align: right;
        }

        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .header-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message {
            color: #4a5568;
            margin-bottom: 24px;
            font-size: 15px;
        }

        .btn {
            display: inline-block;
            background: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            margin: 16px 0;
        }

        .token-box {
            background: #f7fafc;
            border: 1px dashed #cbd5e0;
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 13px;
            color: #2d3748;
        }

        .footer {
            background: #f7fafc;
            padding: 24px 30px;
            text-align: center;
            color: #718096;
            font-size: 13px;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="header">
            <div class="header-title">إعادة تعيين كلمة المرور</div>
        </div>
        <div class="content">
            <p class="greeting">مرحباً {{ $user->name }}،</p>
            <p class="message">
                تلقينا طلباً لإعادة تعيين كلمة المرور لحسابك في {{ config('app.name') }}.
                اضغط على الزر أدناه لإكمال العملية. الرابط صالح لمدة 60 دقيقة.
            </p>
            <p style="text-align: center;">
                <a href="{{ $resetUrl }}" class="btn">إعادة تعيين كلمة المرور</a>
            </p>
            <p class="message">
                إذا لم يعمل الزر، انسخ الرابط التالي إلى المتصفح:
            </p>
            <p class="token-box">{{ $resetUrl }}</p>
            <p class="message">
                لتطبيق الجوال: افتح شاشة «إعادة تعيين كلمة المرور» وأدخل البريد الإلكتروني والرمز التالي:
            </p>
            <p class="token-box">{{ $token }}</p>
            <p class="message" style="margin-top: 24px; color: #a0aec0; font-size: 13px;">
                إذا لم تطلب إعادة التعيين، يمكنك تجاهل هذه الرسالة.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. جميع الحقوق محفوظة.
        </div>
    </div>
</body>

</html>
