@component('mail::message')
    # Payment Reminder

    Dear {{ $item->installment->customer->name }},

    This is a friendly reminder that your installment payment is due **{{ $daysRemaining }}
    {{ Str::plural('day', $daysRemaining) }}** from now.

    ## Payment Details
    - **Amount:** ${{ number_format($item->amount, 2) }}
    - **Due Date:** {{ \Carbon\Carbon::parse($item->due_date)->format('F d, Y') }}
    - **Installment ID:** #{{ $item->installment_id }}
    - **Total Installment:** ${{ number_format($item->installment->total_amount, 2) }}

    Please ensure payment is made by the due date to avoid any late fees.

    If you have already made this payment, please ignore this reminder.

    @component('mail::panel')
        Need assistance? Please contact us at **{{ config('mail.from.address') }}** or call our support line.
    @endcomponent

    Thank you for your prompt attention to this matter.

    Best regards,<br>
    **{{ config('app.name') }}**<br>
    {{ config('mail.from.name') }}
@endcomponent
