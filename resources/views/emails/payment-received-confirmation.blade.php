@component('mail::message')
    # Payment Received - Thank You

    Dear {{ $item->installment->customer->name }},

    We have successfully received your payment. Thank you.

    ## Payment Confirmation

    **Amount Received:** ${{ number_format($paidAmount, 2) }}
    **Date Received:** {{ $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('F d, Y') : date('F d, Y') }}
    @if ($item->reference)
        **Reference:** {{ $item->reference }}
    @endif
    **Installment ID:** {{ $item->installment_id }}

    ## Installment Status

    **Total Amount:** ${{ number_format($item->installment->total_amount, 2) }}
    **Payment Schedule:** {{ $item->installment->months }} months
    **Remaining Balance:**
    ${{ number_format($item->installment->total_amount - $item->installment->items()->whereNotNull('paid_at')->sum('paid_amount'), 2) }}

    ---

    Your payment has been processed and your account has been updated.

    We appreciate your business.

    Best regards,
    **{{ config('mail.from.name') }}**
    {{ config('app.name') }}

    ---

    <small style="color: #9ca3af;">
        For questions, contact us at {{ config('mail.from.address') }}
    </small>
@endcomponent
