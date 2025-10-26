@component('mail::message')
    # 🎉 New Installment Plan Created

    Dear **{{ $installment->customer->name }}**,

    Thank you for choosing our services! Your installment plan has been **successfully created** and activated. We are
    pleased to help you manage your payments with our flexible installment system.

    ---

    ## 📋 Installment Summary

    @component('mail::panel')
        **Installment Details:**

        - **Total Amount:** **${{ number_format($installment->total_amount, 2) }}**
        - **Payment Duration:** **{{ $installment->months }} {{ Str::plural('month', $installment->months) }}**
        - **Start Date:** {{ $installment->start_date->format('F d, Y') }}
        - **End Date:** {{ $installment->end_date->format('F d, Y') }}
        - **Status:** <span style="color: #22c55e; font-weight: bold;">Active ✓</span>
    @endcomponent

    @if ($installment->products)
        ## 📦 Products & Services

        @foreach ($installment->products as $product)
            • {{ $product }}
        @endforeach
    @endif

    ---

    ## 💰 Complete Payment Schedule

    Below is your complete payment schedule. Please ensure all payments are made on or before the due dates specified:

    @component('mail::table')
        | Payment | Due Date | Amount |
        |:-------:|:--------|-------:|
        @foreach ($installment->items as $index => $item)
            | #{{ $index + 1 }} | {{ \Carbon\Carbon::parse($item->due_date)->format('M d, Y') }} |
            **${{ number_format($item->amount, 2) }}** |
        @endforeach
    @endcomponent

    **Grand Total:** **${{ number_format($installment->total_amount, 2) }}**

    ---

    ## ⏰ Your Next Payment

    @component('mail::panel', ['color' => '#3b82f6'])
        ### 🗓️ Upcoming Payment

        **Payment Amount:** **${{ number_format($installment->items->first()->amount ?? 0, 2) }}**
        **Due Date:** **{{ $installment->items->first()?->due_date->format('l, F d, Y') }}**
        **Payment #:** 1 of {{ $installment->months }}
    @endcomponent

    ---

    ## ℹ️ Important Information

    @component('mail::panel')
        ### Payment Guidelines

        ✓ **Prompt Payment:** Ensure timely payment of each installment by the due date
        ✓ **Late Fees:** Late payments may incur additional charges
        ✓ **Payment Methods:** Contact us for available payment methods
        ✓ **Support:** For assistance, reach us at **{{ config('mail.from.address') }}**

        **Please save this email for your records.** 📧
    @endcomponent

    ---

    Thank you for your business! We look forward to a smooth and successful payment journey together.

    Sincerely,
    **{{ config('mail.from.name') }}**
    {{ config('app.name') }}
    {{ config('mail.from.address') }}

    ---

    <small style="color: #6b7280;">
        This is an automated message. Please do not reply to this email.
        For inquiries, please contact us at {{ config('mail.from.address') }}
    </small>
@endcomponent
