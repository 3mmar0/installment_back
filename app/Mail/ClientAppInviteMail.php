<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Installment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientAppInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $playStoreUrl;

    public string $clientPortalUrl;

    public string $appName;

    public string $vendorName;

    public function __construct(
        public Customer $customer,
        public Installment $installment,
        public User $vendor
    ) {
        $this->playStoreUrl = (string) config('app.mobile_app.play_store_url');
        $this->clientPortalUrl = (string) config('app.client_portal_url');
        $this->appName = (string) config('app.mobile_app.name', config('app.name'));
        $this->vendorName = $vendor->name ?: (string) config('app.name');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تابع أقساطك وادفع بسهولة عبر تطبيق '.$this->appName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-app-invite',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
