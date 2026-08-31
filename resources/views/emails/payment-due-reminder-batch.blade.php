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
            <div class="header-subtitle">{{ $items->count() }} {{ $items->count() === 1 ? 'دفعة' : 'دفعات' }} قريبة الاستحقاق</div>
        </div>

        <div class="content">
            <div class="greeting">مرحباً {{ $customer->name }}،</div>

            <p class="lead">
                لديك {{ $items->count() }} {{ $items->count() === 1 ? 'دفعة' : 'دفعات' }} ستستحق قريباً. يرجى مراجعة التفاصيل أدناه.
            </p>

            <table class="payment-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>خطة التقسيط</th>
                        <th>تاريخ الاستحقاق</th>
                        <th>المبلغ</th>
                        <th>متبقي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $index => $item)
                        @php
                            $daysRemaining = \App\Helpers\InstallmentDateHelper::daysUntilDue($item->due_date);
                            $daysLabel = $daysRemaining === 1 ? 'يوم' : 'أيام';
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>#{{ $item->installment_id }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') }}</td>
                            <td class="amount">@money($item->amount)</td>
                            <td>{{ $daysRemaining }} {{ $daysLabel }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="notice-box">
                <div class="notice-title">تذكير الدفع</div>
                <p class="notice-list" style="margin: 0;">
                    يرجى سداد الدفعات قبل تاريخ الاستحقاق لتجنب أي رسوم تأخير.
                </p>
            </div>

            <p class="lead">
                إذا كنت قد دفعت بالفعل، يرجى تجاهل هذا التذكير.<br>
                للمساعدة: <strong>{{ config('mail.from.address') }}</strong>
            </p>
        </div>

        @include('emails.partials.footer')
    </div>
</body>

</html>
