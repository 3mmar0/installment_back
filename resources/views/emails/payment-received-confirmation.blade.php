@component('mail::message')
    # Payment Receipt

    Dear {{ $item->installment->customer->name }},

    We have successfully received your payment. This email serves as your receipt.

    ---

    ## Payment Details

    **Payment Amount:** ${{ number_format($paidAmount, 2) }}
    **Payment Date:** {{ $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('F d, Y') : date('F d, Y') }}
    @if ($item->reference)
        **Reference Number:** {{ $item->reference }}
    @endif
    **Installment ID:** {{ $item->installment_id }}

    ---

    ## Installment Summary

    **Total Amount:** ${{ number_format($item->installment->total_amount, 2) }}
    **Payment Schedule:** {{ $item->installment->months }} months
    **Remaining Balance:**
    ${{ number_format($item->installment->total_amount - $item->installment->items()->whereNotNull('paid_at')->sum('paid_amount'), 2) }}

    ---

    Your payment has been processed and your account has been updated.

    Please keep this email for your records.

    Best regards,
    **{{ config('mail.from.name') }}**
    {{ config('app.name') }}
    {{ config('mail.from.address') }}

    ---

    <small style="color: #9ca3af;">
        This is your payment receipt. For questions, contact {{ config('mail.from.address') }}
    </small>
@endcomponent
