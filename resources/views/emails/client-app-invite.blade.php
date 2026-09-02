<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="ar">
    <title>ثبّت التطبيق وتابع أقساطك - {{ $appName }}</title>
    @include('emails.partials.styles')
    <style>
        .cta-stack {
            margin: 28px 0 8px;
            text-align: center;
        }

        .cta-btn {
            display: inline-block;
            text-decoration: none !important;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            padding: 14px 28px;
            margin: 8px 6px;
            line-height: 1.4;
        }

        .cta-btn--primary {
            background: linear-gradient(135deg, #1B4F9C 0%, #163f7d 100%);
            color: #ffffff !important;
            box-shadow: 0 8px 18px rgba(27, 79, 156, 0.28);
        }

        .cta-btn--secondary {
            background: #ffffff;
            color: #1B4F9C !important;
            border: 2px solid #1B4F9C;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 18px 0 8px;
        }

        .feature-list li {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 10px;
            color: #334155;
            font-size: 14px;
        }

        .store-note {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            margin-top: 8px;
        }

        .plan-chip {
            display: inline-block;
            background: #e8f0fa;
            color: #1B4F9C;
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 700;
            margin-top: 8px;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="header">
            <img src="{{ config('app.url') }}/aqsat-logo.png" alt="{{ $appName }}" class="header-logo" />
            <div class="header-title">أقساطك أصبحت جاهزة للمتابعة</div>
            <div class="header-subtitle">ثبّت التطبيق لتتبع الدفعات وإبلاغ السداد بسهولة</div>
        </div>

        <div class="content">
            <div class="greeting">مرحباً {{ $customer->name }}،</div>

            <p class="lead">
                أنشأ <strong>{{ $vendorName }}</strong> خطة تقسيط باسمك.
                يمكنك الآن متابعة جدول الدفعات وإرسال طلبات السداد مع إثبات الدفع عبر تطبيق
                <strong>{{ $appName }}</strong>.
            </p>

            <div class="plan-summary-hero">
                <div class="plan-summary-hero-label">ملخص خطتك</div>
                <div class="plan-summary-hero-amount">@money($installment->total_amount)</div>
                <div class="plan-summary-hero-meta">
                    {{ $installment->months }} {{ $installment->months == 1 ? 'شهر' : 'أشهر' }}
                    @if ($installment->name)
                        · {{ $installment->name }}
                    @endif
                </div>
                <div class="plan-summary-hero-id">رقم الخطة #{{ $installment->id }}</div>
            </div>

            <h3 class="section-title">ماذا يمكنك فعله في التطبيق؟</h3>
            <ul class="feature-list">
                <li>✓ متابعة جميع أقساطك ومواعيد الاستحقاق في مكان واحد</li>
                <li>✓ معرفة المتبقي والمدفوع والمتأخر فوراً</li>
                <li>✓ إرسال طلب تأكيد دفع مع صورة أو ملف الإثبات</li>
                <li>✓ استلام تنبيهات عند اقتراب موعد القسط</li>
            </ul>

            <div class="cta-stack">
                <a href="{{ $playStoreUrl }}" class="cta-btn cta-btn--primary" target="_blank" rel="noopener">
                    تثبيت التطبيق من Google Play
                </a>
                <br>
                <a href="{{ $clientPortalUrl }}" class="cta-btn cta-btn--secondary" target="_blank" rel="noopener">
                    إنشاء حساب من الموقع
                </a>
            </div>
            <p class="store-note">
                سجّل بنفس البريد الإلكتروني
                <strong style="direction: ltr; unicode-bidi: isolate; display: inline-block;">{{ $customer->email }}</strong>
                لربط أقساطك تلقائياً.
            </p>

            <div class="notice-box notice-box--success" style="margin-top: 28px;">
                <div class="notice-title">خطوة سريعة للبدء</div>
                <ul class="notice-list">
                    <li>1) ثبّت التطبيق أو افتح رابط التسجيل</li>
                    <li>2) أنشئ حسابك وأكّد بريدك برمز التحقق</li>
                    <li>3) ستظهر أقساطك تلقائياً ويمكنك متابعة الدفع</li>
                </ul>
            </div>

            <p class="lead" style="margin-top: 24px;">
                إذا واجهت أي صعوبة، تواصل مع البائع
                <strong>{{ $vendorName }}</strong>
                أو راسلنا على
                <strong>{{ config('mail.from.address') }}</strong>.
            </p>

            <p class="lead">مع تحيات فريق {{ config('app.name') }}</p>
        </div>

        @include('emails.partials.footer', [
            'footerNote' => 'تم إرسال هذه الدعوة لأن البائع أنشأ خطة تقسيط مرتبطة ببريدك.',
        ])
    </div>
</body>

</html>
