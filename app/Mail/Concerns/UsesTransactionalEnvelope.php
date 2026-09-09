<?php

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;

trait UsesTransactionalEnvelope
{
    protected function transactionalEnvelope(string $subject, string $tag): Envelope
    {
        $replyToAddress = (string) config('mail.reply_to.address');
        $replyTo = filter_var($replyToAddress, FILTER_VALIDATE_EMAIL)
            ? [new Address($replyToAddress, (string) config('mail.reply_to.name'))]
            : [];

        return new Envelope(
            subject: $subject,
            replyTo: $replyTo,
            tags: [$tag],
            metadata: ['category' => 'transactional'],
        );
    }
}
