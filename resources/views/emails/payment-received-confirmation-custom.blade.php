<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="ar">
    <title>تم استلام الدفعة - {{ config('app.name') }}</title>
    @include('emails.partials.styles')
</head>

<body>
    @php
        $paidTotal = $item->installment->items()->whereNotNull('paid_at')->sum('paid_amount');
        $remaining = $item->installment->total_amount - $paidTotal;
    @endphp

    <div class="email-container">
        <div class="header header--success">
            <img src="{{ config('app.url') }}/aqsat-logo.png" alt="اقساطي" class="header-logo" />
            <div class="header-title">تم استلام الدفعة</div>
            <div class="header-subtitle">شكراً لك على الدفع</div>
        </div>

        <div class="content">
            <div class="greeting">مرحباً {{ $item->installment->customer->name }}،</div>

            <p class="lead">
                لقد استلمنا دفعتك بنجاح. هذا البريد بمثابة إيصال الدفع الخاص بك.
            </p>

            <div class="notice-box notice-box--success">
                <div class="notice-title">تم تأكيد الدفع</div>
                <p class="notice-list" style="margin: 0;">تمت معالجة دفعتك وتحديث حسابك.</p>
            </div>

            <div class="receipt-box">
                <div class="highlight-label">المبلغ المستلم</div>
                <div class="receipt-amount">@money($paidAmount)</div>
                <div class="highlight-date">
                    التاريخ:
                    {{ $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('d/m/Y') : date('d/m/Y') }}<br>
                    @if ($item->reference)
                        المرجع: {{ $item->reference }}<br>
                    @endif
                    رقم الخطة: #{{ $item->installment_id }}
                </div>
            </div>

            <h3 class="section-title">ملخص الحساب</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">المبلغ الإجمالي</div>
                    <div class="info-value">@money($item->installment->total_amount)</div>
                </div>
                <div class="info-item">
                    <div class="info-label">فترة الدفع</div>
                    <div class="info-value">{{ $item->installment->months }}
                        {{ $item->installment->months == 1 ? 'شهر' : 'أشهر' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">المدفوع</div>
                    <div class="info-value success">@money($paidTotal)</div>
                </div>
                <div class="info-item">
                    <div class="info-label">المتبقي</div>
                    <div class="info-value">@money($remaining)</div>
                </div>
            </div>

            <p class="lead" style="text-align: center; font-weight: 700;">
                يرجى الاحتفاظ بهذا البريد للمحفوظات.
            </p>

            <p class="lead">شكراً لك على تعاملك معنا!</p>
        </div>

        @include('emails.partials.footer', ['footerNote' => 'هذا إيصال الدفع الخاص بك.'])
    </div>
</body>

</html>
