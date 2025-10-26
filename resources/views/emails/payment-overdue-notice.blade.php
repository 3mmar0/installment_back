@component('mail::message')
    # Payment Overdue Notice

    Dear {{ $item->installment->customer->name }},

    We noticed that your installment payment has been **overdue for {{ $daysOverdue }}
    {{ Str::plural('day', $daysOverdue) }}**.

    ## Overdue Payment Details
    - **Amount Due:** ${{ number_format($item->amount, 2) }}
    - **Original Due Date:** {{ \Carbon\Carbon::parse($item->due_date)->format('F d, Y') }}
    - **Days Overdue:** {{ $daysOverdue }}
    - **Installment ID:** #{{ $item->installment_id }}

    @component('mail::panel')
        **Action Required:** Please make the payment as soon as possible to avoid additional fees or account restrictions.
    @endcomponent

    If you have already made this payment, please contact us immediately at **{{ config('mail.from.address') }}** so we can
    update your account.

    We understand that sometimes circumstances can make it difficult to pay on time. If you're experiencing financial
    difficulties, please reach out to us to discuss payment options.

    Thank you for your immediate attention to this matter.

    Best regards,<br>
    **{{ config('app.name') }}**<br>
    {{ config('mail.from.name') }}
@endcomponent
