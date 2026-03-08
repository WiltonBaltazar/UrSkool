<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SubscriptionExpired extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $firstName,
        public string $planName,
        public string $expiredAt,
        public string $renewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'A sua subscrição expirou',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscriptions.expired',
            with: [
                'firstName' => $this->firstName,
                'planName' => $this->planName,
                'expiredAt' => $this->expiredAt,
                'renewUrl' => $this->renewUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

