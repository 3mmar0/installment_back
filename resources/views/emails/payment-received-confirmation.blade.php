@component('mail::message')
    # Payment Received - Thank You!

    Dear {{ $item->installment->customer->name }},

    We have successfully received your payment. Thank you for your prompt payment!

    ## Payment Confirmation
    - **Amount Received:** ${{ number_format($paidAmount, 2) }}
    - **Date Received:** {{ $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('F d, Y') : 'Today' }}
    @if ($item->reference)
        - **Reference:** {{ $item->reference }}
    @endif
    - **Installment ID:** #{{ $item->installment_id }}

    @component('mail::panel')
        Your payment has been processed and your account has been updated accordingly.
    @endcomponent

    ## Installment Status
    - **Total Amount:** ${{ number_format($item->installment->total_amount, 2) }}
    - **Payment Schedule:** {{ $item->installment->months }} months
    - **Remaining Balance:**
    ${{ number_format($item->installment->total_amount - $item->installment->items()->whereNotNull('paid_at')->sum('paid_amount'), 2) }}

    We appreciate your business and look forward to serving you again.

    If you have any questions or concerns, please don't hesitate to contact us at **{{ config('mail.from.address') }}**.

    Best regards,<br>
    **{{ config('app.name') }}**<br>
    {{ config('mail.from.name') }}
@endcomponent
