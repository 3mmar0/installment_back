<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installment Plan Created - {{ config('app.name') }}</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .info-box {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #718096;
            font-weight: 500;
        }

        .info-value {
            color: #2d3748;
            font-weight: 600;
        }

        .highlight-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin: 25px 0;
            text-align: center;
        }

        .highlight-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .highlight-amount {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
        }

        .highlight-date {
            font-size: 16px;
        }

        .next-payment {
            background: #f0fff4;
            border-left: 4px solid #48bb78;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }

        .payment-table th,
        .payment-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .payment-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }

        .payment-table td {
            color: #2d3748;
        }

        .payment-table tr:last-child td {
            border-bottom: none;
        }

        .reminder-box {
            background: #fffbf0;
            border-left: 4px solid #f6ad55;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }

        .reminder-title {
            font-size: 16px;
            font-weight: 600;
            color: #c05621;
            margin-bottom: 10px;
        }

        .reminder-list {
            color: #744210;
            font-size: 14px;
            line-height: 1.8;
        }

        .reminder-list li {
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

            .highlight-amount {
                font-size: 28px;
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
            <div class="header-title">🎉 Your Installment Plan is Ready</div>
            <div class="header-subtitle">We're excited to have you on board</div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Hello {{ $installment->customer->name }},
            </div>

            <p style="margin-bottom: 20px; color: #4a5568;">
                Your installment plan has been successfully created and activated. Below are your payment details.
            </p>

            <!-- Plan Overview -->
            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Total Amount</span>
                    <span class="info-value"
                        style="color: #667eea;">${{ number_format($installment->total_amount, 2) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Duration</span>
                    <span class="info-value">{{ $installment->months }}
                        {{ Str::plural('month', $installment->months) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Start Date</span>
                    <span class="info-value">{{ $installment->start_date->format('M d, Y') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">End Date</span>
                    <span class="info-value">{{ $installment->end_date->format('M d, Y') }}</span>
                </div>
            </div>

            @if ($installment->products)
                <div style="margin: 25px 0;">
                    <h3 style="color: #2d3748; margin-bottom: 15px; font-size: 18px;">Products & Services</h3>
                    @foreach ($installment->products as $product)
                        <div style="padding: 8px 0; color: #4a5568;">✓ {{ $product }}</div>
                    @endforeach
                </div>
            @endif

            <!-- Payment Schedule -->
            <h3 style="color: #2d3748; margin: 30px 0 15px; font-size: 18px;">Payment Schedule</h3>
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Due Date</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($installment->items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->due_date)->format('M d, Y') }}</td>
                            <td style="text-align: right; font-weight: 600; color: #667eea;">
                                ${{ number_format($item->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Next Payment Highlight -->
            <div class="highlight-box">
                <div class="highlight-label">Your Next Payment</div>
                <div class="highlight-amount">${{ number_format($installment->items->first()->amount ?? 0, 2) }}</div>
                <div class="highlight-date">Due {{ $installment->items->first()?->due_date->format('M d, Y') }}</div>
            </div>

            <!-- Important Information -->
            <div class="reminder-box">
                <div class="reminder-title">📋 Important Information</div>
                <ul class="reminder-list" style="list-style: none; padding-left: 0;">
                    <li>✓ Make payments on or before the due date</li>
                    <li>✓ Late payments may incur additional fees</li>
                    <li>✓ Keep this email for your records</li>
                    <li>✓ Contact us anytime you need help</li>
                </ul>
            </div>

            <p style="margin: 25px 0; color: #4a5568; text-align: center;">
                Questions? We're here to help at <strong>{{ config('mail.from.address') }}</strong>
            </p>

            <p style="color: #4a5568; margin-top: 20px;">
                Thanks for choosing {{ config('app.name') }}!
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
