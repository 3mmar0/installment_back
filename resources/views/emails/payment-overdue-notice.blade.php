@component('mail::message')
    # ⚠️ Payment Overdue Notice

    Dear **{{ $item->installment->customer->name }}**,

    We are writing to inform you that your installment payment has been **overdue for {{ $daysOverdue }}
    {{ Str::plural('day', $daysOverdue) }}**.

    ---

    ## 🔴 Overdue Payment Information

    @component('mail::panel', ['color' => '#ef4444'])
        **Urgent Payment Details:**

        - **Amount Due:** **${{ number_format($item->amount, 2) }}**
        - **Original Due Date:** {{ \Carbon\Carbon::parse($item->due_date)->format('l, F d, Y') }}
        - **Days Overdue:** **{{ $daysOverdue }} {{ Str::plural('day', $daysOverdue) }}**
        - **Installment Plan #:** {{ $item->installment_id }}
        - **Current Total:** ${{ number_format($item->installment->total_amount, 2) }}
    @endcomponent

    ---

    ## ⚡ Immediate Action Required

    @component('mail::panel')
        ### 🚨 Important Notice

        **Please make this payment as soon as possible** to avoid:
        - Additional late fees or penalties
        - Account restrictions or service suspension
        - Negative impact on your payment history

        **Immediate payment is strongly recommended.**
    @endcomponent

    ---

    ## 💡 We're Here to Help

    @component('mail::panel')
        ### Payment Assistance Available

        We understand that unforeseen circumstances can arise. If you're experiencing financial difficulties:

        ✓ **Contact us immediately** to discuss payment options
        ✓ **We can work together** to find a solution that works for you
        ✓ **Early communication** helps us assist you better

        **Don't wait – reach out today!**
    @endcomponent

    ---

    ## 📞 Contact Information

    Please contact us immediately:

    - **Email:** {{ config('mail.from.address') }}
    - **Installment ID:** #{{ $item->installment_id }}
    - **Support:** Available Monday - Friday, 9:00 AM - 5:00 PM

    **If you have already made this payment,** please contact us immediately at **{{ config('mail.from.address') }}** so we
    can update your account and avoid any further notices.

    ---

    ## ✅ Next Steps

    1. **Review** your payment details above
    2. **Make payment** immediately or contact us
    3. **Confirm** payment with our office if needed

    We appreciate your immediate attention to this matter and look forward to resolving this quickly.

    Sincerely,
    **{{ config('mail.from.name') }}**
    {{ config('app.name') }}
    {{ config('mail.from.address') }}

    ---

    <small style="color: #6b7280;">
        **⚠️ Urgent:** This payment requires immediate attention.
        This is an automated notice. Please contact us directly at {{ config('mail.from.address') }}
    </small>
@endcomponent
