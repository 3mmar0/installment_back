@component('mail::message')
    # Payment Reminder

    Dear {{ $item->installment->customer->name }},

    This is a friendly reminder that your payment is due in **{{ $daysRemaining }}
    {{ Str::plural('day', $daysRemaining) }}**.

    ## Payment Details

    **Amount Due:** ${{ number_format($item->amount, 2) }}
    **Due Date:** {{ \Carbon\Carbon::parse($item->due_date)->format('F d, Y') }}
    **Installment ID:** {{ $item->installment_id }}

    ---

    Please make your payment by the due date to avoid late fees.

    If you have already made this payment, please ignore this reminder.

    For questions or assistance, contact us at **{{ config('mail.from.address') }}**.

    Thank you for your prompt attention.

    Best regards,
    **{{ config('mail.from.name') }}**
    {{ config('app.name') }}

    ---

    <small style="color: #9ca3af;">
        For inquiries, contact us at {{ config('mail.from.address') }}
    </small>
@endcomponent
