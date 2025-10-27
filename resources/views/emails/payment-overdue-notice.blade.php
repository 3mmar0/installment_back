@component('mail::message', ['color' => '#ef4444'])
    <style>
        .header-gradient {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            padding: 40px 20px;
            text-align: center;
            color: white;
        }

        .critical-box {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
        }

        .payment-card {
            background: white;
            border: 2px solid #ef4444;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
        }
    </style>

    <div class="header-gradient">
        <h1 style="margin: 0; font-size: 28px; font-weight: 700;">⚠️ Payment Overdue</h1>
        <p style="margin: 10px 0 0; font-size: 16px; opacity: 0.9;">Action required</p>
    </div>

    @component('mail::message')
        Hello **{{ $item->installment->customer->name }}**,

        Your payment has been overdue for **{{ $daysOverdue }} {{ Str::plural('day', $daysOverdue) }}**.

        <div class="critical-box">
            <div style="color: #991b1b; font-weight: 700; font-size: 16px; margin-bottom: 10px;">🚨 URGENT PAYMENT REQUIRED</div>
            <div style="color: #991b1b;">Please make this payment immediately to avoid additional fees or restrictions.</div>
        </div>

        ## Overdue Payment

        <div class="payment-card">
            <div style="color: #991b1b; font-size: 14px; margin-bottom: 10px;">Amount Overdue</div>
            <div style="font-size: 36px; font-weight: 700; color: #ef4444; margin: 10px 0;">
                ${{ number_format($item->amount, 2) }}</div>
            <div style="color: #6b7280; margin-top: 10px;">
                Was Due: {{ \Carbon\Carbon::parse($item->due_date)->format('F d, Y') }}
            </div>
            <div style="color: #ef4444; margin-top: 5px; font-weight: 600;">
                {{ $daysOverdue }} days overdue
            </div>
        </div>

        ## What You Need to Do

        ✓ **Make payment immediately**
        ✓ **Contact us if you're facing financial difficulties**
        ✓ **We can help find a solution**

        ---

        If you've already paid, please contact us at **{{ config('mail.from.address') }}** to update your account.

        We understand circumstances can be difficult. Contact us to discuss payment options.

        **{{ config('mail.from.address') }}**

        Thank you for your immediate attention.

        {{ config('app.name') }} Team
        <small style="color: #9ca3af;">{{ config('mail.from.address') }}</small>
    @endcomponent

@endcomponent
