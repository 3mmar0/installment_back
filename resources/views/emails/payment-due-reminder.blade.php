@component('mail::message')
    # ⏰ Payment Reminder

    Dear **{{ $item->installment->customer->name }}**,

    This is a **friendly reminder** that your installment payment will be due in **{{ $daysRemaining }}
    {{ Str::plural('day', $daysRemaining) }}**.

    ---

    ## 💳 Payment Details

    @component('mail::panel')
        **Payment Information:**

        - **Amount Due:** **${{ number_format($item->amount, 2) }}**
        - **Due Date:** **{{ \Carbon\Carbon::parse($item->due_date)->format('l, F d, Y') }}**
        - **Days Remaining:** {{ $daysRemaining }} {{ Str::plural('day', $daysRemaining) }}
        - **Installment Plan #:** {{ $item->installment_id }}
        - **Total Installment:** ${{ number_format($item->installment->total_amount, 2) }}
    @endcomponent

    ---

    ## ✅ Action Required

    To ensure smooth processing and avoid any late fees, please make your payment **before the due date**.

    @component('mail::panel', ['color' => '#f59e0b'])
        ### 📝 Important Note

        If you have already made this payment, please **ignore this reminder**.
        If you are unable to make payment by the due date, please contact us immediately.
    @endcomponent

    ---

    ## 📞 Need Assistance?

    We're here to help! For any questions or concerns:

    - **Email:** {{ config('mail.from.address') }}
    - **Support Hours:** Monday - Friday, 9:00 AM - 5:00 PM
    - **Installment ID:** #{{ $item->installment_id }}

    Thank you for your prompt attention to this matter.

    Best regards,
    **{{ config('mail.from.name') }}**
    {{ config('app.name') }}
    {{ config('mail.from.address') }}

    ---

    <small style="color: #6b7280;">
        This is an automated reminder. Please do not reply to this email.
        For inquiries, please contact us at {{ config('mail.from.address') }}
    </small>
@endcomponent
