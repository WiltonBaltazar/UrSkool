<?php

namespace App\Mail;

use App\Models\Plan;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WelcomeUser extends Mailable
{
    use Queueable;

    /**
     * Auto-discard queued jobs when serialized models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    public string $firstName;
    public string $planName;
    public float $amountPaid;
    public string $paymentReference;
    public string $validUntil;
    public string $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Plan $plan, Subscription $subscription)
    {
        $this->firstName = (string) ($user->first_name ?: 'Utilizador');
        $this->planName = (string) ($plan->name ?: 'Plano');
        $this->amountPaid = (float) ($subscription->amount_paid ?? $plan->effective_price ?? $plan->price ?? 0);
        $this->paymentReference = (string) ($subscription->payment_reference ?: '-');
        $this->validUntil = $subscription->end_date
            ? $subscription->end_date->format('d/m/Y')
            : '-';
        $this->loginUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/') . '/login';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bem-vindo à Lenda +!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'firstName' => $this->firstName,
                'planName' => $this->planName,
                'amountPaid' => $this->amountPaid,
                'paymentReference' => $this->paymentReference,
                'validUntil' => $this->validUntil,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }
}
