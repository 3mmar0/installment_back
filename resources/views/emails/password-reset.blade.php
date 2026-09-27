<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="ar">
    <title>إعادة تعيين كلمة المرور - {{ config('app.name') }}</title>
    @include('emails.partials.styles')
    <style>
        .otp-box {
            background: #f8fafc;
            border: 2px solid #1B4F9C;
            padding: 20px 28px;
            border-radius: 12px;
            margin: 20px auto;
            font-family: monospace;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 12px;
            color: #0f172a;
            direction: ltr;
            text-align: center;
            unicode-bidi: isolate;
            max-width: 280px;
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
                استخدم الرمز التالي في التطبيق أو الموقع. الرمز صالح لمدة 60 دقيقة.
            </p>
            <p class="otp-box">{{ $code }}</p>
            <p class="lead" style="font-size: 18px; font-weight: 700; color: #b45309; line-height: 1.8;">
                إذا لم تجد هذه الرسالة في صندوق الوارد، ابحث عنها في مجلد غير المرغوب فيه أو الرسائل المزعجة أو العروض (Spam).
            </p>
            <p class="lead" style="color: #94a3b8; font-size: 13px;">
                إذا لم تطلب إعادة التعيين، يمكنك تجاهل هذه الرسالة.
            </p>
        </div>

        @include('emails.partials.footer')
    </div>
</body>

</html>
