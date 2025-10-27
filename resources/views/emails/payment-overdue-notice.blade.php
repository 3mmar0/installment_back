@component('mail::message')
    # Payment Overdue Notice

    Dear {{ $item->installment->customer->name }},

    Your payment has been overdue for **{{ $daysOverdue }} {{ Str::plural('day', $daysOverdue) }}**.

    ## Overdue Payment Details

    **Amount Due:** ${{ number_format($item->amount, 2) }}
    **Due Date:** {{ \Carbon\Carbon::parse($item->due_date)->format('F d, Y') }}
    **Days Overdue:** {{ $daysOverdue }}
    **Installment ID:** {{ $item->installment_id }}

    ---

    ## Action Required

    Please make this payment immediately to avoid additional fees or account restrictions.

    If you have already made this payment, please contact us at **{{ config('mail.from.address') }}** so we can update your
    account.

    We understand that circumstances can make payment difficult. If you're experiencing financial difficulties, please reach
    out to discuss payment options.

    Contact us at **{{ config('mail.from.address') }}** for assistance.

    Thank you for your immediate attention.

    Best regards,
    **{{ config('mail.from.name') }}**
    {{ config('app.name') }}

    ---

    <small style="color: #9ca3af;">
        For urgent matters, contact us at {{ config('mail.from.address') }}
    </small>
@endcomponent
