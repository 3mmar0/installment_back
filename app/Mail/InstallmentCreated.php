<?php

namespace App\Mail;

use App\Mail\Concerns\UsesTransactionalEnvelope;
use App\Models\Installment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstallmentCreated extends Mailable
{
    use Queueable, SerializesModels, UsesTransactionalEnvelope;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Installment $installment,
        public string $recipientEmail
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return $this->transactionalEnvelope('تم إنشاء خطة التقسيط - جدول الدفع', 'installment-created');
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.installment-created-custom',
            text: 'emails.text.installment-created',
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
