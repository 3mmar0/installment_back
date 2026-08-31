<?php

namespace App\Mail;

use App\Models\InstallmentItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentOverdueNotice extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public InstallmentItem $item,
        public int $daysOverdue
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $daysLabel = $this->daysOverdue === 1 ? 'يوم' : 'أيام';

        return new Envelope(
            subject: "عاجل: تأخر الدفع {$this->daysOverdue} {$daysLabel}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-overdue-notice-custom',
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
