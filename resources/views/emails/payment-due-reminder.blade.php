@component('mail::message')
    # ⏰ Payment Due Reminder

    Hello {{ $item->installment->customer->name }},

    This is a friendly reminder that your payment is due in **{{ $daysRemaining }}
    {{ Str::plural('day', $daysRemaining) }}**.

    ## Payment Details

    @component('mail::panel')
        **Amount Due:** ${{ number_format($item->amount, 2) }}
        **Due Date:** {{ \Carbon\Carbon::parse($item->due_date)->format('F d, Y') }}
        **Plan ID:** #{{ $item->installment_id }}
    @endcomponent

    Please make your payment by the due date to avoid late fees.

    If you've already paid, please ignore this reminder.

    For questions or assistance, contact us at **{{ config('mail.from.address') }}**.

    Thanks for your prompt attention!

    Best regards,
    **{{ config('app.name') }} Team**
    {{ config('mail.from.address') }}
@endcomponent
