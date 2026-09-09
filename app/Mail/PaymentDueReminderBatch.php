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

class PaymentDueReminderBatch extends Mailable
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
        $label = $count === 1 ? 'دفعة' : 'دفعات';

        return $this->transactionalEnvelope(
            "تذكير: {$count} {$label} قريبة الاستحقاق",
            'payment-due-reminder'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-due-reminder-batch',
            text: 'emails.text.payment-due-reminder-batch',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
