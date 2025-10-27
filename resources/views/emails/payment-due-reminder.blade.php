@component('mail::message', ['color' => '#f59e0b'])
    <style>
        .header-gradient {
            background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            padding: 40px 20px;
            text-align: center;
            color: white;
        }

        .urgent-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
        }

        .payment-card {
            background: white;
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
        }
    </style>

    <div class="header-gradient">
        <h1 style="margin: 0; font-size: 28px; font-weight: 700;">Payment Reminder</h1>
        <p style="margin: 10px 0 0; font-size: 16px; opacity: 0.9;">Your payment is due soon</p>
    </div>

    @component('mail::message')
        Hello **{{ $item->installment->customer->name }}**,

        This is a friendly reminder that your payment is due in **{{ $daysRemaining }}
        {{ Str::plural('day', $daysRemaining) }}**.

        <div class="urgent-box">
            <div style="color: #92400e; font-weight: 600; font-size: 14px; margin-bottom: 10px;">⏰ PAYMENT REMINDER</div>
            <div style="color: #92400e;">Please make your payment before the due date to avoid late fees.</div>
        </div>

        ## Payment Details

        <div class="payment-card">
            <div style="color: #92400e; font-size: 14px; margin-bottom: 10px;">Amount Due</div>
            <div style="font-size: 36px; font-weight: 700; color: #f59e0b; margin: 10px 0;">
                ${{ number_format($item->amount, 2) }}</div>
            <div style="color: #6b7280; margin-top: 10px;">
                Due: {{ \Carbon\Carbon::parse($item->due_date)->format('F d, Y') }}
            </div>
            <div style="color: #6b7280; margin-top: 5px; font-size: 13px;">
                Plan #{{ $item->installment_id }}
            </div>
        </div>

        ---

        **If you've already paid, please ignore this reminder.**

        Need help? Contact us at **{{ config('mail.from.address') }}**

        Thank you!

        {{ config('app.name') }} Team
        <small style="color: #9ca3af;">{{ config('mail.from.address') }}</small>
    @endcomponent

@endcomponent
