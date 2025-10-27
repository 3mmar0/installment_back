<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأخر في الدفع - {{ config('app.name') }}</title>
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
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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

        .critical-box {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }

        .critical-title {
            font-size: 16px;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 10px;
        }

        .critical-text {
            color: #991b1b;
            font-size: 14px;
        }

        .payment-card {
            background: white;
            border: 3px solid #ef4444;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 25px 0;
        }

        .payment-label {
            color: #991b1b;
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .payment-amount {
            font-size: 42px;
            font-weight: bold;
            color: #ef4444;
            margin: 15px 0;
        }

        .payment-details {
            color: #6b7280;
            margin-top: 15px;
            font-size: 14px;
        }

        .action-box {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }

        .action-title {
            font-size: 16px;
            font-weight: 600;
            color: #92400e;
            margin-bottom: 10px;
        }

        .action-list {
            color: #92400e;
            font-size: 14px;
            line-height: 1.8;
        }

        .action-list li {
            margin-bottom: 8px;
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

            .payment-amount {
                font-size: 32px;
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
            <div class="header-title">⚠️ تأخر في الدفع</div>
            <div class="header-subtitle">إجراء مطلوب</div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                مرحباً {{ $item->installment->customer->name }}،
            </div>

            <p style="margin-bottom: 20px; color: #4a5568;">
                دفعتك متأخرة لمدة **{{ $daysOverdue }} {{ $daysOverdue == 1 ? 'يوم' : 'أيام' }}**.
            </p>

            <div class="critical-box">
                <div class="critical-title">🚨 دفع عاجل مطلوب</div>
                <div class="critical-text">يرجى سداد هذه الدفعة فوراً لتجنب رسوم إضافية أو قيود على الحساب.</div>
            </div>

            <!-- Overdue Payment Details -->
            <div class="payment-card">
                <div class="payment-label">المبلغ المتأخر</div>
                <div class="payment-amount">${{ number_format($item->amount, 2) }}</div>
                <div class="payment-details">
                    كان مستحق: {{ \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') }}<br>
                    <span style="color: #ef4444; font-weight: 600;">{{ $daysOverdue }}
                        {{ $daysOverdue == 1 ? 'يوم' : 'أيام' }} متأخر</span><br>
                    رقم الخطة: #{{ $item->installment_id }}
                </div>
            </div>

            <!-- Action Required -->
            <div class="action-box">
                <div class="action-title">📋 ما تحتاج لفعله</div>
                <ul class="action-list" style="list-style: none; padding-right: 0;">
                    <li>✓ سدد الدفعة فوراً</li>
                    <li>✓ تواصل معنا إذا كنت تواجه صعوبات مالية</li>
                    <li>✓ يمكننا مساعدتك في إيجاد حل</li>
                </ul>
            </div>

            <p style="margin: 25px 0; color: #4a5568; text-align: center;">
                <strong>إذا كنت قد دفعت بالفعل،</strong> يرجى التواصل معنا على
                <strong>{{ config('mail.from.address') }}</strong> لتحديث حسابك.
            </p>

            <p style="color: #4a5568;">
                نحن نفهم أن الظروف قد تكون صعبة. تواصل معنا لمناقشة خيارات الدفع.
            </p>

            <p style="margin-top: 20px; color: #4a5568;">
                تواصل معنا: <strong>{{ config('mail.from.address') }}</strong>
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
                هذه إشعار عاجل.
            </div>
        </div>
    </div>
</body>

</html>
