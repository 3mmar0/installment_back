@component('mail::message')
    # ⚠️ Payment Overdue - Action Required

    Hello {{ $item->installment->customer->name }},

    Your payment has been overdue for **{{ $daysOverdue }} {{ Str::plural('day', $daysOverdue) }}**.

    ## Overdue Payment Details

    @component('mail::panel')
        **Amount Due:** ${{ number_format($item->amount, 2) }}
        **Original Due Date:** {{ \Carbon\Carbon::parse($item->due_date)->format('F d, Y') }}
        **Days Overdue:** {{ $daysOverdue }}
        **Plan ID:** #{{ $item->installment_id }}
    @endcomponent

    ## What You Need to Do

    Please make this payment immediately to avoid:
    - Additional late fees or penalties
    - Account restrictions
    - Negative impact on your payment history

    ## We're Here to Help

    If you're experiencing financial difficulties, please contact us immediately to discuss payment options.

    **We can work together to find a solution that works for you.**

    Contact us at **{{ config('mail.from.address') }}** for assistance.

    If you've already made this payment, please contact us immediately so we can update your account.

    Thank you for your immediate attention to this matter.

    Best regards,
    **{{ config('app.name') }} Team**
    {{ config('mail.from.address') }}
@endcomponent
