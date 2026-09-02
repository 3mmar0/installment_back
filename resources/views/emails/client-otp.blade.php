<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="ar">
    <title>رمز التحقق - {{ config('app.name') }}</title>
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
            max-width: 240px;
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
            <div class="header-title">تأكيد البريد الإلكتروني</div>
        </div>

        <div class="content">
            <p class="greeting">مرحباً{{ $client->name ? ' ' . $client->name : '' }}،</p>
            <p class="lead">
                استخدم الرمز التالي لتأكيد بريدك الإلكتروني في {{ config('app.name') }}.
                الرمز صالح لمدة 10 دقائق.
            </p>
            <p class="otp-box">{{ $otpCode }}</p>
            <p class="lead" style="color: #94a3b8; font-size: 13px;">
                إذا لم تطلب هذا الرمز، يمكنك تجاهل هذه الرسالة.
            </p>
        </div>

        @include('emails.partials.footer')
    </div>
</body>

</html>
