@component('mail::message')
    # 🎉 Your Installment Plan is Ready

    Hello {{ $installment->customer->name }},

    Your installment plan has been set up successfully. We're excited to have you on board!

    ## Plan Overview

    - **Total Amount:** ${{ number_format($installment->total_amount, 2) }}
    - **Duration:** {{ $installment->months }} {{ Str::plural('month', $installment->months) }}
    - **Start Date:** {{ $installment->start_date->format('M d, Y') }}
    - **End Date:** {{ $installment->end_date->format('M d, Y') }}

    @if ($installment->products)
        ## What You're Getting

        @foreach ($installment->products as $product)
            - {{ $product }}
        @endforeach
    @endif

    ## Your Payment Schedule

    @component('mail::table')
        | Payment # | Due Date | Amount |
        |:---------:|:--------|-------:|
        @foreach ($installment->items as $index => $item)
            | {{ $index + 1 }} | {{ \Carbon\Carbon::parse($item->due_date)->format('M d, Y') }} |
            **${{ number_format($item->amount, 2) }}** |
        @endforeach
    @endcomponent

    ## Your Next Payment

    @component('mail::panel')
        **Amount:** ${{ number_format($installment->items->first()->amount ?? 0, 2) }}
        **Due Date:** {{ $installment->items->first()?->due_date->format('M d, Y') }}
    @endcomponent

    ## What to Remember

    - Make payments on or before the due date
    - Late payments may incur additional fees
    - Keep this email for your records
    - Contact us anytime you need help

    @component('mail::button', ['url' => config('app.url')])
        View Your Account
    @endcomponent

    Questions? We're here to help at **{{ config('mail.from.address') }}**

    Thanks for choosing {{ config('app.name') }}!

    Best regards,
    **{{ config('app.name') }} Team**
@endcomponent
