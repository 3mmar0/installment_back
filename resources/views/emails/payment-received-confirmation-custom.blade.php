<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تم استلام الدفعة - {{ config('app.name') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Tahoma', 'Arial', sans-serif;
            line-height: 1.8;
            color: #333;
            background-color: #f4f6f8;
            direction: rtl;
            text-align: right;
        }

        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .header-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .header-subtitle {
            font-size: 16px;
            opacity: 0.9;
        }

        .content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .success-box {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }

        .success-title {
            font-size: 16px;
            font-weight: 600;
            color: #065f46;
            margin-bottom: 10px;
        }

        .success-text {
            color: #065f46;
            font-size: 14px;
        }

        .receipt-box {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin: 25px 0;
            text-align: center;
        }

        .receipt-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .receipt-amount {
            font-size: 42px;
            font-weight: bold;
            margin: 15px 0;
        }

        .receipt-details {
            margin-top: 20px;
            font-size: 14px;
            opacity: 0.9;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 25px 0;
        }

        .info-item {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .info-label {
            color: #718096;
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .info-value {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
        }

        .info-value.success {
            color: #10b981;
        }

        .footer {
            background-color: #2d3748;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .footer-logo {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .footer-text {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 15px;
        }

        .copyright {
            font-size: 12px;
            opacity: 0.6;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 20px;
            margin-top: 20px;
        }

        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 8px;
            }

            .header,
            .content,
            .footer {
                padding: 20px;
            }

            .receipt-amount {
                font-size: 32px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .header-title {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="header-title">✅ تم استلام الدفعة</div>
            <div class="header-subtitle">شكراً لك على الدفع</div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                مرحباً {{ $item->installment->customer->name }}،
            </div>

            <p style="margin-bottom: 20px; color: #4a5568;">
                لقد استلمنا دفعتك بنجاح. هذا البريد الإلكتروني بمثابة إيصال الدفع الخاص بك.
            </p>

            <div class="success-box">
                <div class="success-title">✓ تم تأكيد الدفع</div>
                <div class="success-text">تمت معالجة دفعتك وتحديث حسابك.</div>
            </div>

            <!-- Payment Amount -->
            <div class="receipt-box">
                <div class="receipt-label">المبلغ المستلم</div>
                <div class="receipt-amount">${{ number_format($paidAmount, 2) }}</div>
                <div class="receipt-details">
                    التاريخ:
                    {{ $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('d/m/Y') : date('d/m/Y') }}<br>
                    @if ($item->reference)
                        المرجع: {{ $item->reference }}<br>
                    @endif
                    رقم الخطة: #{{ $item->installment_id }}
                </div>
            </div>

            <!-- Account Summary -->
            <h3 style="color: #2d3748; margin: 25px 0 15px; font-size: 18px;">ملخص الحساب</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">المبلغ الإجمالي</div>
                    <div class="info-value">${{ number_format($item->installment->total_amount, 2) }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">فترة الدفع</div>
                    <div class="info-value">{{ $item->installment->months }}
                        {{ $item->installment->months == 1 ? 'شهر' : 'أشهر' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">المدفوع</div>
                    <div class="info-value success">
                        ${{ number_format($item->installment->items()->whereNotNull('paid_at')->sum('paid_amount'), 2) }}
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">المتبقي</div>
                    <div class="info-value">
                        ${{ number_format($item->installment->total_amount - $item->installment->items()->whereNotNull('paid_at')->sum('paid_amount'), 2) }}
                    </div>
                </div>
            </div>

            <p style="margin: 25px 0; color: #4a5568; text-align: center; font-weight: 600;">
                يرجى الاحتفاظ بهذا البريد الإلكتروني للمحفوظات.
            </p>

            <p style="color: #4a5568;">
                شكراً لك على عملك!
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">{{ config('app.name') }}</div>
            <div class="footer-text">
                نظام إدارة التقسيط الاحترافي
            </div>
            <div class="copyright">
                © {{ date('Y') }} {{ config('app.name') }}. جميع الحقوق محفوظة.<br>
                هذا إيصال الدفع الخاص بك.
            </div>
        </div>
    </div>
</body>

</html>
