@component('mail::message', ['color' => '#6366f1'])
    <style>
        .header-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 20px;
            text-align: center;
            color: white;
        }

        .content-block {
            background: #f8fafc;
            padding: 30px 20px;
            margin: 20px 0;
            border-radius: 12px;
            border-left: 4px solid #6366f1;
        }

        .highlight-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: none;
        }
    </style>

    <div class="header-gradient">
        <h1 style="margin: 0; font-size: 28px; font-weight: 700;">Your Installment Plan is Ready</h1>
        <p style="margin: 10px 0 0; font-size: 16px; opacity: 0.9;">We're excited to have you on board</p>
    </div>

    @component('mail::message')
        Hello **{{ $installment->customer->name }}**,

        Your installment plan has been set up successfully. Here's everything you need to know.

        ## Plan Overview

        <div class="content-block">
            <div class="info-row">
                <span><strong>Total Amount:</strong></span>
                <span style="color: #6366f1; font-weight: 700;">${{ number_format($installment->total_amount, 2) }}</span>
            </div>
            <div class="info-row">
                <span><strong>Duration:</strong></span>
                <span>{{ $installment->months }} {{ Str::plural('month', $installment->months) }}</span>
            </div>
            <div class="info-row">
                <span><strong>Start Date:</strong></span>
                <span>{{ $installment->start_date->format('M d, Y') }}</span>
            </div>
            <div class="info-row">
                <span><strong>End Date:</strong></span>
                <span>{{ $installment->end_date->format('M d, Y') }}</span>
            </div>
        </div>

        @if ($installment->products)
            ## What You're Getting

            @foreach ($installment->products as $product)
                ✓ {{ $product }}
            @endforeach
        @endif

        ## Your Payment Schedule

        @component('mail::table')
            | Payment | Due Date | Amount |
            |:-------:|---------:|-------:|
            @foreach ($installment->items as $index => $item)
                | {{ $index + 1 }} | {{ \Carbon\Carbon::parse($item->due_date)->format('M d, Y') }} |
                **${{ number_format($item->amount, 2) }}** |
            @endforeach
        @endcomponent

        ## Your Next Payment

        <div class="highlight-box">
            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Upcoming Payment</div>
            <div style="font-size: 32px; font-weight: 700; margin: 10px 0;">
                ${{ number_format($installment->items->first()->amount ?? 0, 2) }}</div>
            <div style="font-size: 16px;">Due {{ $installment->items->first()?->due_date->format('M d, Y') }}</div>
        </div>

        ## What to Remember

        ✓ Make payments on or before the due date
        ✓ Late payments may incur additional fees
        ✓ Keep this email for your records
        ✓ Contact us anytime you need help

        ---

        Questions? We're here to help
        **{{ config('mail.from.address') }}**

        Thanks for choosing {{ config('app.name') }}!

        <div style="text-align: center; padding: 30px 0 10px;">
            <strong style="color: #6366f1;">{{ config('app.name') }} Team</strong>
        </div>
    @endcomponent

@endcomponent
