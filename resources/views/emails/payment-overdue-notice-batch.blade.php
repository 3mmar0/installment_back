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
    @php
        $totalOverdue = $items->sum('amount');
    @endphp

    <div class="email-container">
        <div class="header header--danger">
            <img src="{{ config('app.url') }}/aqsat-logo.png" alt="اقساطي" class="header-logo" />
            <div class="header-title">تأخر في الدفع</div>
            <div class="header-subtitle">{{ $items->count() }} {{ $items->count() === 1 ? 'دفعة متأخرة' : 'دفعات متأخرة' }}</div>
        </div>

        <div class="content">
            <div class="greeting">مرحباً {{ $customer->name }}،</div>

            <p class="lead">
                لديك {{ $items->count() }} {{ $items->count() === 1 ? 'دفعة متأخرة' : 'دفعات متأخرة' }}.
                يرجى السداد في أقرب وقت ممكن.
            </p>

            <div class="notice-box notice-box--danger">
                <div class="notice-title">دفع عاجل مطلوب</div>
                <p class="notice-list" style="margin: 0;">
                    إجمالي المبالغ المتأخرة: <strong>@money($totalOverdue)</strong>
                </p>
            </div>

            <table class="payment-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>خطة التقسيط</th>
                        <th>كان مستحقاً</th>
                        <th>المبلغ</th>
                        <th>التأخير</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $index => $item)
                        @php
                            $daysOverdue = now()->diffInDays($item->due_date);
                            $daysLabel = $daysOverdue === 1 ? 'يوم' : 'أيام';
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>#{{ $item->installment_id }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') }}</td>
                            <td class="amount">@money($item->amount)</td>
                            <td>{{ $daysOverdue }} {{ $daysLabel }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="notice-box">
                <div class="notice-title">ما تحتاج لفعله</div>
                <ul class="notice-list">
                    <li>✓ سدد الدفعات المتأخرة في أقرب وقت</li>
                    <li>✓ تواصل معنا إذا كنت تواجه صعوبات مالية</li>
                </ul>
            </div>

            <p class="lead">
                إذا كنت قد دفعت بالفعل، تواصل معنا على
                <strong>{{ config('mail.from.address') }}</strong> لتحديث حسابك.
            </p>
        </div>

        @include('emails.partials.footer', ['footerNote' => 'هذا إشعار عاجل.'])
    </div>
</body>

</html>
