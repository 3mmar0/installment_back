<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Due Reminder - {{ config('app.name') }}</title>
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
            background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
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

        .urgent-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }

        .urgent-title {
            font-size: 16px;
            font-weight: 600;
            color: #92400e;
            margin-bottom: 10px;
        }

        .urgent-text {
            color: #92400e;
            font-size: 14px;
        }

        .payment-card {
            background: white;
            border: 3px solid #f59e0b;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 25px 0;
        }

        .payment-label {
            color: #92400e;
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .payment-amount {
            font-size: 42px;
            font-weight: bold;
            color: #f59e0b;
            margin: 15px 0;
        }

        .payment-details {
            color: #6b7280;
            margin-top: 15px;
            font-size: 14px;
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
            <div class="header-title">⏰ Payment Due Reminder</div>
            <div class="header-subtitle">Your payment is due soon</div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Hello {{ $item->installment->customer->name }},
            </div>

            <p style="margin-bottom: 20px; color: #4a5568;">
                This is a friendly reminder that your payment is due in **{{ $daysRemaining }}
                {{ Str::plural('day', $daysRemaining) }}**.
            </p>

            <div class="urgent-box">
                <div class="urgent-title">⏰ Payment Reminder</div>
                <div class="urgent-text">Please make your payment before the due date to avoid late fees.</div>
            </div>

            <!-- Payment Details -->
            <div class="payment-card">
                <div class="payment-label">Amount Due</div>
                <div class="payment-amount">${{ number_format($item->amount, 2) }}</div>
                <div class="payment-details">
                    Due: {{ \Carbon\Carbon::parse($item->due_date)->format('F d, Y') }}<br>
                    Plan: #{{ $item->installment_id }}
                </div>
            </div>

            <p style="margin: 25px 0; color: #4a5568; text-align: center;">
                <strong>If you've already paid, please ignore this reminder.</strong>
            </p>

            <p style="margin: 20px 0; color: #4a5568;">
                Need help? Contact us at <strong>{{ config('mail.from.address') }}</strong>
            </p>

            <p style="color: #4a5568;">
                Thank you!
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
                This email was sent automatically, please do not reply.
            </div>
        </div>
    </div>
</body>

</html>
