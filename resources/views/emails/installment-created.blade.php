@component('mail::message')
    # New Installment Plan Created

    Dear Customer,

    Your new installment plan has been successfully created. Below are the details of your payment schedule.

    ## Installment Summary

    - **Total Amount:** ${{ number_format($installment->total_amount, 2) }}
    - **Number of Payments:** {{ $installment->months }} {{ Str::plural('month', $installment->months) }}
    - **Start Date:** {{ $installment->start_date->format('F d, Y') }}
    - **End Date:** {{ $installment->end_date->format('F d, Y') }}
    - **Status:** Active

    @if ($installment->products)
        ## Products/Services

        @foreach ($installment->products as $product)
            - {{ $product }}
        @endforeach
    @endif

    ## Payment Schedule

    Your payments are scheduled as follows:

    | Payment # | Due Date | Amount |
    |-----------|----------|--------|
    @foreach ($installment->items as $index => $item)
        | {{ $index + 1 }} | {{ \Carbon\Carbon::parse($item->due_date)->format('M d, Y') }} |
        ${{ number_format($item->amount, 2) }} |
    @endforeach

    **Total Amount:** ${{ number_format($installment->total_amount, 2) }}

    ## Important Information

    @component('mail::panel')
        Please note:

        - Ensure timely payment of each installment by the due date
        - Late payments may incur additional fees
        - All payments should be made according to the scheduled dates above

        If you have any questions or need assistance, please contact us at **{{ config('mail.from.address') }}**.
    @endcomponent

    ## Next Payment

    Your first payment of **${{ number_format($installment->items->first()->amount ?? 0, 2) }}** is due on
    **{{ $installment->items->first()?->due_date->format('F d, Y') }}**.

    Please save this email for your records.

    Thank you for choosing **{{ config('app.name') }}**!

    Best regards,<br>
    **{{ config('mail.from.name') }}**<br>
    {{ config('app.name') }}
@endcomponent
