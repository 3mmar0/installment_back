@component('mail::message')
    # New Installment Plan Created

    Dear {{ $installment->customer->name }},

    Your installment plan has been successfully created. Here are your payment details.

    ---

    ## Installment Details

    **Total Amount:** ${{ number_format($installment->total_amount, 2) }}
    **Payment Period:** {{ $installment->months }} {{ Str::plural('month', $installment->months) }}
    **Start Date:** {{ $installment->start_date->format('F d, Y') }}
    **End Date:** {{ $installment->end_date->format('F d, Y') }}

    @if ($installment->products)
        ## Products & Services

        @foreach ($installment->products as $product)
            • {{ $product }}
        @endforeach
    @endif

    ---

    ## Payment Schedule

    @component('mail::table')
        | Payment | Due Date | Amount |
        |:-------:|:--------|-------:|
        @foreach ($installment->items as $index => $item)
            | {{ $index + 1 }} | {{ \Carbon\Carbon::parse($item->due_date)->format('M d, Y') }} |
            ${{ number_format($item->amount, 2) }} |
        @endforeach
    @endcomponent

    ---

    ## Next Payment

    **Payment Amount:** ${{ number_format($installment->items->first()->amount ?? 0, 2) }}
    **Due Date:** {{ $installment->items->first()?->due_date->format('F d, Y') }}

    ---

    ## Important Information

    Please make each payment by the due date to avoid late fees.

    If you have any questions, please contact us at **{{ config('mail.from.address') }}**.

    ---

    Thank you for choosing {{ config('app.name') }}.

    Best regards,
    **{{ config('mail.from.name') }}**
    {{ config('app.name') }}

    ---

    <small style="color: #9ca3af;">
        For inquiries, contact us at {{ config('mail.from.address') }}
    </small>
@endcomponent
