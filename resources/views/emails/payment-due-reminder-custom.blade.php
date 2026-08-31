<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="ar">
    <title>تذكير بموعد الدفع - {{ config('app.name') }}</title>
    @include('emails.partials.styles')
</head>

<body>
    <div class="email-container">
        <div class="header header--warning">
            <img src="{{ config('app.url') }}/aqsat-logo.png" alt="اقساطي" class="header-logo" />
            <div class="header-title">تذكير بموعد الدفع</div>
            <div class="header-subtitle">موعد الدفع الخاص بك قريب</div>
        </div>

        <div class="content">
            <div class="greeting">مرحباً {{ $item->installment->customer->name }}،</div>

            <p class="lead">
                هذا تذكير بأن موعد الدفع خلال {{ $daysRemaining }} {{ $daysRemaining == 1 ? 'يوم' : 'أيام' }}.
            </p>

            <div class="notice-box">
                <div class="notice-title">تذكير الدفع</div>
                <p class="notice-list" style="margin: 0;">
                    يرجى سداد الدفعة قبل تاريخ الاستحقاق لتجنب أي رسوم تأخير.
                </p>
            </div>

            <div class="payment-card">
                <div class="payment-card-label">المبلغ المستحق</div>
                <div class="payment-card-amount">@money($item->amount)</div>
                <div class="payment-card-details">
                    موعد الاستحقاق: {{ \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') }}<br>
                    رقم الخطة: #{{ $item->installment_id }}
                </div>
            </div>

            <p class="lead" style="text-align: center; font-weight: 700;">
                إذا كنت قد دفعت بالفعل، يرجى تجاهل هذا التذكير.
            </p>

            <p class="lead">
                تحتاج مساعدة؟ تواصل معنا على <strong>{{ config('mail.from.address') }}</strong>
            </p>

            <p class="lead">شكراً لك!</p>
        </div>

        @include('emails.partials.footer')
    </div>
</body>

</html>
