<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyNewEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Auto-discard queued jobs when serialized models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    protected string $newEmail;

    public function __construct(string $newEmail)
    {
        $this->newEmail = $newEmail;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'profile.email.confirm',
            now()->addMinutes(60),
            ['userId' => $notifiable->id]
        );

        return (new MailMessage)
            ->subject('Confirme o seu novo e-mail')
            ->view('emails.auth.verify-new-email', [
                'firstName' => $notifiable->first_name ?? 'Utilizador',
                'newEmail' => $this->newEmail,
                'verificationUrl' => $verificationUrl,
            ]);
    }
}
