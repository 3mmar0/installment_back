<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="ar">
    <title>تأخر في الدفع - {{ config('app.name') }}</title>
    @include('emails.partials.styles')
</head>

<body>
    <div class="email-container">
        <div class="header header--danger">
            <img src="{{ config('app.url') }}/aqsat-logo.png" alt="اقساطي" class="header-logo" />
            <div class="header-title">تأخر في الدفع</div>
            <div class="header-subtitle">إجراء مطلوب</div>
        </div>

        <div class="content">
            <div class="greeting">مرحباً {{ $item->installment->customer->name }}،</div>

            <p class="lead">
                دفعتك متأخرة لمدة {{ $daysOverdue }} {{ $daysOverdue == 1 ? 'يوم' : 'أيام' }}.
            </p>

            <div class="notice-box notice-box--danger">
                <div class="notice-title">دفع عاجل مطلوب</div>
                <p class="notice-list" style="margin: 0;">
                    يرجى سداد هذه الدفعة فوراً لتجنب رسوم إضافية أو قيود على الحساب.
                </p>
            </div>

            <div class="payment-card payment-card--danger">
                <div class="payment-card-label">المبلغ المتأخر</div>
                <div class="payment-card-amount">@money($item->amount)</div>
                <div class="payment-card-details">
                    كان مستحقاً: {{ \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') }}<br>
                    <span style="color: #ef4444; font-weight: 700;">{{ $daysOverdue }}
                        {{ $daysOverdue == 1 ? 'يوم' : 'أيام' }} متأخر</span><br>
                    رقم الخطة: #{{ $item->installment_id }}
                </div>
            </div>

            <div class="notice-box">
                <div class="notice-title">ما تحتاج لفعله</div>
                <ul class="notice-list">
                    <li>✓ سدد الدفعة فوراً</li>
                    <li>✓ تواصل معنا إذا كنت تواجه صعوبات مالية</li>
                    <li>✓ يمكننا مساعدتك في إيجاد حل</li>
                </ul>
            </div>

            <p class="lead" style="text-align: center; font-weight: 700;">
                إذا كنت قد دفعت بالفعل، يرجى التواصل معنا على
                <strong>{{ config('mail.from.address') }}</strong> لتحديث حسابك.
            </p>

            <p class="lead">
                نحن نفهم أن الظروف قد تكون صعبة. تواصل معنا لمناقشة خيارات الدفع على
                <strong>{{ config('mail.from.address') }}</strong>
            </p>
        </div>

        @include('emails.partials.footer', ['footerNote' => 'هذا إشعار عاجل.'])
    </div>
</body>

</html>
