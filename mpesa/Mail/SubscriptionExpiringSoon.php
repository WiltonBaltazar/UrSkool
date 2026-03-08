<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SubscriptionExpiringSoon extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $firstName,
        public string $planName,
        public string $expiresAt,
        public int $daysRemaining,
        public string $renewUrl,
    ) {}

    public function envelope(): Envelope
    {
        $daysLabel = $this->daysRemaining === 1 ? '1 dia' : "{$this->daysRemaining} dias";

        return new Envelope(
            subject: "A sua subscrição expira em {$daysLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscriptions.expiring-soon',
            with: [
                'firstName' => $this->firstName,
                'planName' => $this->planName,
                'expiresAt' => $this->expiresAt,
                'daysRemaining' => $this->daysRemaining,
                'renewUrl' => $this->renewUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
