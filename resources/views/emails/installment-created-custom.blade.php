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

            @php
                $firstItem = $installment->items->first();
                $monthlyAmount =
                    $firstItem?->amount ??
                    ($installment->months > 0 ? round($installment->total_amount / $installment->months, 2) : null);
                $installmentCount = $installment->items->count() ?: $installment->months;
            @endphp

            <h3 class="section-title" style="margin-top: 0;">ملخص الخطة</h3>

            <div class="plan-summary-hero">
                <div class="plan-summary-hero-label">المبلغ الإجمالي</div>
                <div class="plan-summary-hero-amount">@money($installment->total_amount)</div>
                @if ($monthlyAmount)
                    <div class="plan-summary-hero-meta">
                        {{ $installment->months }} {{ $installment->months == 1 ? 'قسط' : 'أقساط' }}
                        · @money($monthlyAmount) شهرياً
                    </div>
                @endif
                @if ($installment->name)
                    <div class="plan-summary-hero-meta">{{ $installment->name }}</div>
                @endif
                <div class="plan-summary-hero-id">رقم الخطة #{{ $installment->id }}</div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">المدة</div>
                    <div class="info-value">{{ $installment->months }}
                        {{ $installment->months == 1 ? 'شهر' : 'أشهر' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">عدد الأقساط</div>
                    <div class="info-value">{{ $installmentCount }}
                        {{ $installmentCount == 1 ? 'دفعة' : 'دفعات' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">تاريخ البداية</div>
                    <div class="info-value info-value--date">{{ $installment->start_date->format('d/m/Y') }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">تاريخ الانتهاء</div>
                    <div class="info-value info-value--date">{{ $installment->end_date->format('d/m/Y') }}</div>
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
