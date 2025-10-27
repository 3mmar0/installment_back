<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Received - {{ config('app.name') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f6f8;
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
            <div class="header-title">✅ Payment Received</div>
            <div class="header-subtitle">Thank you for your payment</div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Hello {{ $item->installment->customer->name }},
            </div>

            <p style="margin-bottom: 20px; color: #4a5568;">
                We have successfully received your payment. This email serves as your payment receipt.
            </p>

            <div class="success-box">
                <div class="success-title">✓ Payment Confirmed</div>
                <div class="success-text">Your payment has been processed and your account has been updated.</div>
            </div>

            <!-- Payment Amount -->
            <div class="receipt-box">
                <div class="receipt-label">Amount Received</div>
                <div class="receipt-amount">${{ number_format($paidAmount, 2) }}</div>
                <div class="receipt-details">
                    Date:
                    {{ $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('M d, Y') : date('M d, Y') }}<br>
                    @if ($item->reference)
                        Ref: {{ $item->reference }}<br>
                    @endif
                    Plan ID: #{{ $item->installment_id }}
                </div>
            </div>

            <!-- Account Summary -->
            <h3 style="color: #2d3748; margin: 25px 0 15px; font-size: 18px;">Account Summary</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Total Amount</div>
                    <div class="info-value">${{ number_format($item->installment->total_amount, 2) }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Payment Period</div>
                    <div class="info-value">{{ $item->installment->months }} months</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Paid</div>
                    <div class="info-value success">
                        ${{ number_format($item->installment->items()->whereNotNull('paid_at')->sum('paid_amount'), 2) }}
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Remaining</div>
                    <div class="info-value">
                        ${{ number_format($item->installment->total_amount - $item->installment->items()->whereNotNull('paid_at')->sum('paid_amount'), 2) }}
                    </div>
                </div>
            </div>

            <p style="margin: 25px 0; color: #4a5568; text-align: center; font-weight: 600;">
                Please keep this email for your records.
            </p>

            <p style="color: #4a5568;">
                Thank you for your business!
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">{{ config('app.name') }}</div>
            <div class="footer-text">
                Professional installment management system
            </div>
            <div class="copyright">
                © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                This is your payment receipt.
            </div>
        </div>
    </div>
</body>

</html>
