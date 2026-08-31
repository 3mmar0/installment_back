<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class PaymentOverdueNoticeBatch extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\InstallmentItem>  $items
     */
    public function __construct(
        public Customer $customer,
        public Collection $items,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->items->count();
        $label = $count === 1 ? 'دفعة متأخرة' : 'دفعات متأخرة';

        return new Envelope(
            subject: "عاجل: {$count} {$label}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-overdue-notice-batch',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
