@component('mail::message', ['color' => '#10b981'])
    <style>
        .header-gradient {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 40px 20px;
            text-align: center;
            color: white;
        }

        .success-box {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
        }

        .receipt-card {
            background: white;
            border: 2px solid #10b981;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }

        .info-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
        }
    </style>

    <div class="header-gradient">
        <h1 style="margin: 0; font-size: 28px; font-weight: 700;">✓ Payment Received</h1>
        <p style="margin: 10px 0 0; font-size: 16px; opacity: 0.9;">Thank you for your payment</p>
    </div>

    @component('mail::message')
        Hello **{{ $item->installment->customer->name }}**,

        We have successfully received and processed your payment.

        <div class="success-box">
            <div style="color: #065f46; font-weight: 700; font-size: 16px; margin-bottom: 8px;">✓ PAYMENT CONFIRMED</div>
            <div style="color: #065f46;">Your payment has been processed and your account has been updated.</div>
        </div>

        ## Payment Receipt

        <div class="receipt-card">
            <div style="color: #065f46; font-size: 14px; margin-bottom: 10px;">Amount Received</div>
            <div style="font-size: 40px; font-weight: 700; color: #10b981; margin: 10px 0;">${{ number_format($paidAmount, 2) }}
            </div>
            <div style="color: #6b7280; margin-top: 15px;">
                Date: {{ $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('M d, Y') : date('M d, Y') }}
                @if ($item->reference)
                    <br>Ref: {{ $item->reference }}
                @endif
            </div>
        </div>

        ## Account Summary

        <div class="info-grid">
            <div class="info-item">
                <div style="color: #6b7280; font-size: 13px;">Total Amount</div>
                <div style="font-size: 18px; font-weight: 700; margin-top: 5px;">
                    ${{ number_format($item->installment->total_amount, 2) }}</div>
            </div>
            <div class="info-item">
                <div style="color: #6b7280; font-size: 13px;">Payment Period</div>
                <div style="font-size: 18px; font-weight: 700; margin-top: 5px;">{{ $item->installment->months }} months</div>
            </div>
            <div class="info-item">
                <div style="color: #6b7280; font-size: 13px;">Paid</div>
                <div style="font-size: 18px; font-weight: 700; color: #10b981; margin-top: 5px;">
                    ${{ number_format($item->installment->items()->whereNotNull('paid_at')->sum('paid_amount'), 2) }}
                </div>
            </div>
            <div class="info-item">
                <div style="color: #6b7280; font-size: 13px;">Remaining</div>
                <div style="font-size: 18px; font-weight: 700; margin-top: 5px;">
                    ${{ number_format($item->installment->total_amount - $item->installment->items()->whereNotNull('paid_at')->sum('paid_amount'), 2) }}
                </div>
            </div>
        </div>

        ---

        **Please keep this email for your records.** This serves as your payment receipt.

        Plan ID: #{{ $item->installment_id }}

        ---

        Questions? We're here to help
        **{{ config('mail.from.address') }}**

        Thank you for your business!

        {{ config('app.name') }} Team
    @endcomponent

@endcomponent
