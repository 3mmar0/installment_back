<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="ar">
    <title>تم إنشاء خطة التقسيط - {{ config('app.name') }}</title>
    @include('emails.partials.styles')
</head>

<body>
    <div class="email-container">
        <div class="header">
            <img src="{{ config('app.url') }}/aqsat-logo.png" alt="اقساطي" class="header-logo" />
            <div class="header-title">تم إنشاء خطة التقسيط</div>
            <div class="header-subtitle">جدول الدفع الخاص بك جاهز</div>
        </div>

        <div class="content">
            <div class="greeting">مرحباً {{ $installment->customer->name }}،</div>

            <p class="lead">
                تم إنشاء خطة التقسيط الخاصة بك بنجاح. فيما يلي ملخص الخطة وجدول الدفعات.
            </p>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">المبلغ الإجمالي</span>
                    <span class="info-value info-value--accent">@money($installment->total_amount)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">المدة</span>
                    <span class="info-value">{{ $installment->months }}
                        {{ $installment->months == 1 ? 'شهر' : 'أشهر' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">تاريخ البداية</span>
                    <span class="info-value">{{ $installment->start_date->format('d/m/Y') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">تاريخ الانتهاء</span>
                    <span class="info-value">{{ $installment->end_date->format('d/m/Y') }}</span>
                </div>
            </div>

            @if ($installment->products)
                <h3 class="section-title">المنتجات والخدمات</h3>
                @foreach ($installment->products as $product)
                    <div class="product-list-item">✓ {{ $product }}</div>
                @endforeach
            @endif

            <h3 class="section-title">جدول الدفع</h3>
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>تاريخ الاستحقاق</th>
                        <th>المبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($installment->items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') }}</td>
                            <td class="amount">@money($item->amount)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($installment->items->first())
                <div class="highlight-box">
                    <div class="highlight-label">القسط القادم</div>
                    <div class="highlight-amount">@money($installment->items->first()->amount)</div>
                    <div class="highlight-date">
                        مستحق بتاريخ {{ $installment->items->first()->due_date->format('d/m/Y') }}
                    </div>
                </div>
            @endif

            <div class="notice-box">
                <div class="notice-title">معلومات مهمة</div>
                <ul class="notice-list">
                    <li>✓ قم بالدفع في أو قبل تاريخ الاستحقاق</li>
                    <li>✓ قد يؤدي التأخير في الدفع إلى رسوم إضافية</li>
                    <li>✓ احتفظ بهذا البريد للمحفوظات</li>
                    <li>✓ تواصل معنا في أي وقت تحتاج فيه المساعدة</li>
                </ul>
            </div>

            <p class="lead" style="text-align: center; margin-top: 24px;">
                لديك أسئلة؟ نحن هنا لمساعدتك على
                <strong>{{ config('mail.from.address') }}</strong>
            </p>

            <p class="lead">شكراً لاختيارك {{ config('app.name') }}!</p>
        </div>

        @include('emails.partials.footer')
    </div>
</body>

</html>
