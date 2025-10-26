@component('mail::message')
    # ✅ Payment Received - Thank You!

    Dear **{{ $item->installment->customer->name }}**,

    We are pleased to confirm that we have **successfully received your payment**. Thank you for your timely payment!

    ---

    ## 💰 Payment Confirmation

    @component('mail::panel', ['color' => '#22c55e'])
        **Payment Details:**

        ✓ **Amount Received:** **${{ number_format($paidAmount, 2) }}**
        ✓ **Date Received:**
        {{ $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('l, F d, Y') : date('l, F d, Y') }}
        @if ($item->reference)
            ✓ **Payment Reference:** {{ $item->reference }}
        @endif
        ✓ **Installment Plan #:** {{ $item->installment_id }}
        ✓ **Status:** <span style="color: #22c55e; font-weight: bold;">Processed Successfully ✓</span>
    @endcomponent

    ---

    ## 📊 Your Installment Status

    @component('mail::table')
        | Description | Details |
        |:------------|--------:|
        | Total Amount | **${{ number_format($item->installment->total_amount, 2) }}** |
        | Payment Schedule | {{ $item->installment->months }} months |
        | Installment Paid | **${{ number_format($paidAmount, 2) }}** |
        | Remaining Balance |
        **${{ number_format($item->installment->total_amount - $item->installment->items()->whereNotNull('paid_at')->sum('paid_amount'), 2) }}**
        |
    @endcomponent

    ---

    ## 🎯 Payment Progress

    Your payment has been **processed and your account has been updated** accordingly.

    @component('mail::panel')
        ### ✅ What This Means

        ✓ Your payment has been recorded in our system
        ✓ Your account balance has been updated
        ✓ Your payment history reflects this transaction
        ✓ A receipt confirmation has been generated

        **Please save this email for your records.**
    @endcomponent

    ---

    ## 📋 Important Reminders

    @component('mail::panel')
        ### Upcoming Payments

        Remember to make your remaining payments on time to:
        - Maintain a good payment history
        - Avoid any late fees or penalties
        - Keep your installment plan active
        - Complete your payment schedule successfully

        **Continue to make timely payments for a smooth experience.**
    @endcomponent

    ---

    ## 💌 Questions or Concerns?

    We're here to help! If you have any questions about this payment or your installment plan:

    - **Email:** {{ config('mail.from.address') }}
    - **Installment Plan #:** {{ $item->installment_id }}
    - **Support:** Monday - Friday, 9:00 AM - 5:00 PM

    ---

    ## 🙏 Thank You!

    We truly appreciate your business and prompt payment. Your trust in us means everything, and we're committed to
    providing you with excellent service throughout your installment journey.

    Thank you for being a valued customer!

    Best regards,
    **{{ config('mail.from.name') }}**
    {{ config('app.name') }}
    {{ config('mail.from.address') }}

    ---

    <small style="color: #6b7280;">
        This is an automated confirmation. If you have any questions, please contact us at
        {{ config('mail.from.address') }}
    </small>
@endcomponent
