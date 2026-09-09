<?php

namespace App\Mail;

use App\Mail\Concerns\UsesTransactionalEnvelope;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class PaymentOverdueNoticeBatch extends Mailable
{
    use Queueable, SerializesModels, UsesTransactionalEnvelope;

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

        return $this->transactionalEnvelope("عاجل: {$count} {$label}", 'payment-overdue-notice');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-overdue-notice-batch',
            text: 'emails.text.payment-overdue-notice-batch',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
