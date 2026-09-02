<?php

namespace App\Mail;

use App\Models\ClientAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientEmailOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClientAccount $client,
        public string $otpCode
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رمز التحقق من البريد الإلكتروني - '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-otp',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
