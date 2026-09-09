<?php

namespace App\Mail;

use App\Mail\Concerns\UsesTransactionalEnvelope;
use App\Models\InstallmentItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedConfirmation extends Mailable
{
    use Queueable, SerializesModels, UsesTransactionalEnvelope;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public InstallmentItem $item,
        public float $paidAmount,
        public string $recipientEmail
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return $this->transactionalEnvelope('تم استلام الدفعة - شكراً لك', 'payment-received');
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-received-confirmation-custom',
            text: 'emails.text.payment-received-confirmation',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
