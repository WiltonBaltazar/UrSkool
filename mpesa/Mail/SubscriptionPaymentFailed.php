<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SubscriptionPaymentFailed extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $firstName,
        public string $planName,
        public string $paymentReference,
        public string $retryUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Falha no pagamento da subscrição',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscriptions.payment-failed',
            with: [
                'firstName' => $this->firstName,
                'planName' => $this->planName,
                'paymentReference' => $this->paymentReference,
                'retryUrl' => $this->retryUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

