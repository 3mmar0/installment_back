<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="ar">
    <title>إعادة تعيين كلمة المرور - {{ config('app.name') }}</title>
    @include('emails.partials.styles')
    <style>
        .btn {
            display: inline-block;
            background: #1B4F9C;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 10px;
            font-weight: 700;
            margin: 16px 0;
        }

        .token-box {
            background: #f8fafc;
            border: 1px dashed #cbd5e0;
            padding: 16px;
            border-radius: 10px;
            margin: 16px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 13px;
            color: #0f172a;
            direction: ltr;
            text-align: left;
            unicode-bidi: isolate;
        }

        .footer {
            background: #f8fafc;
            color: #64748b;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="header">
            <img src="{{ config('app.url') }}/aqsat-logo.png" alt="اقساطي" class="header-logo" />
            <div class="header-title">إعادة تعيين كلمة المرور</div>
        </div>

        <div class="content">
            <p class="greeting">مرحباً {{ $user->name }}،</p>
            <p class="lead">
                تلقينا طلباً لإعادة تعيين كلمة المرور لحسابك في {{ config('app.name') }}.
                اضغط على الزر أدناه لإكمال العملية. الرابط صالح لمدة 60 دقيقة.
            </p>
            <p style="text-align: center;">
                <a href="{{ $resetUrl }}" class="btn">إعادة تعيين كلمة المرور</a>
            </p>
            <p class="lead">إذا لم يعمل الزر، انسخ الرابط التالي إلى المتصفح:</p>
            <p class="token-box">{{ $resetUrl }}</p>
            <p class="lead">لتطبيق الجوال: افتح شاشة «إعادة تعيين كلمة المرور» وأدخل البريد الإلكتروني والرمز التالي:</p>
            <p class="token-box">{{ $token }}</p>
            <p class="lead" style="color: #94a3b8; font-size: 13px;">
                إذا لم تطلب إعادة التعيين، يمكنك تجاهل هذه الرسالة.
            </p>
        </div>

        @include('emails.partials.footer')
    </div>
</body>

</html>
