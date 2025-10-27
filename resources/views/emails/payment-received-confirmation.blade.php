@component('mail::message')
    # ✅ Payment Received - Thank You!

    Hello {{ $item->installment->customer->name }},

    We have successfully received your payment. Thank you!

    ## Payment Receipt

    @component('mail::panel')
        **Amount Received:** ${{ number_format($paidAmount, 2) }}
        **Payment Date:** {{ $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('F d, Y') : date('F d, Y') }}
        @if ($item->reference)
            **Reference Number:** {{ $item->reference }}
        @endif
        **Plan ID:** #{{ $item->installment_id }}
    @endcomponent

    ## Your Account Status

    - **Total Amount:** ${{ number_format($item->installment->total_amount, 2) }}
    - **Payment Schedule:** {{ $item->installment->months }} months
    - **Remaining Balance:**
    ${{ number_format($item->installment->total_amount - $item->installment->items()->whereNotNull('paid_at')->sum('paid_amount'), 2) }}

    Your payment has been processed and your account has been updated.

    **Please keep this email for your records as your payment receipt.**

    If you have any questions, feel free to contact us at **{{ config('mail.from.address') }}**.

    We appreciate your business!

    Best regards,
    **{{ config('app.name') }} Team**
    {{ config('mail.from.address') }}
@endcomponent
