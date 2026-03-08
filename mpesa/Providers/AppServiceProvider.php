<?php

namespace App\Providers;

use App\Services\MpesaService;
use App\Services\SubscriptionService;
use App\Support\ResponsiveImageVariants;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // $this->app->singleton(MpesaService::class);
        $this->app->singleton(MpesaService::class);
        $this->app->singleton(SubscriptionService::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerResponsiveImageGenerationHooks();

        RateLimiter::for('auth-login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by($request->ip().'|'.$email),
            ];
        });

        RateLimiter::for('auth-refresh', function (Request $request) {
            $refreshToken = trim((string) $request->cookie('auth_refresh_token', ''));
            $refreshKey = $refreshToken !== '' ? sha1($refreshToken) : 'missing';

            return [
                Limit::perMinute(60)->by('ip:'.$request->ip()),
                Limit::perMinute(20)->by('refresh:'.$refreshKey),
            ];
        });

        RateLimiter::for('auth-register', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(6)->by($request->ip()),
                Limit::perMinute(3)->by($request->ip().'|'.$email),
            ];
        });

        RateLimiter::for('auth-password', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(3)->by($request->ip().'|'.$email),
            ];
        });

        RateLimiter::for('auth-reset', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->ip()),
            ];
        });

        RateLimiter::for('auth-mobile-refresh', function (Request $request) {
            return [
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('push-subscribe', function (Request $request) {
            $userKey = (string) ($request->user()?->id ?? 'guest');

            return [
                Limit::perMinute(30)->by('user:'.$userKey),
                Limit::perMinute(60)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('push-test', function (Request $request) {
            $userKey = (string) ($request->user()?->id ?? 'guest');

            return [
                Limit::perMinute(3)->by('user:'.$userKey),
                Limit::perMinute(10)->by('ip:'.$request->ip()),
            ];
        });

        Model::preventLazyLoading(! app()->isProduction());

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $passwordBroker = (string) config('auth.defaults.passwords', 'users');
        $resetLinkExpiryMinutes = (int) config("auth.passwords.{$passwordBroker}.expire", 60);

        // Custom reset password e-mail
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
            
            return "{$frontendUrl}/reset-password?token={$token}&email={$notifiable->getEmailForPasswordReset()}";
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token) use ($frontendUrl, $resetLinkExpiryMinutes): MailMessage {
            $resetUrl = "{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($notifiable->getEmailForPasswordReset());

            return (new MailMessage)
                ->subject('Redefinir palavra-passe - Lenda +')
                ->view('emails.auth.reset-password', [
                    'firstName' => $notifiable->first_name ?? 'Utilizador',
                    'resetUrl' => $resetUrl,
                    'expiresInMinutes' => $resetLinkExpiryMinutes,
                ]);
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $verificationUrl): MailMessage {
            return (new MailMessage)
                ->subject('Confirme o seu e-mail - Lenda +')
                ->view('emails.auth.verify-email', [
                    'firstName' => $notifiable->first_name ?? 'Utilizador',
                    'verificationUrl' => $verificationUrl,
                ]);
        });
    }

    private function registerResponsiveImageGenerationHooks(): void
    {
        if (! config('media.responsive.enabled', true)) {
            return;
        }

        $targets = config('media.responsive.targets', []);
        if (! is_array($targets) || $targets === []) {
            return;
        }

        /** @var ResponsiveImageVariants $variants */
        $variants = $this->app->make(ResponsiveImageVariants::class);

        foreach ($targets as $modelClass => $attributes) {
            if (! is_string($modelClass) || ! class_exists($modelClass)) {
                continue;
            }

            if (! is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            $imageAttributes = array_values(array_filter(
                is_array($attributes) ? $attributes : [],
                static fn (mixed $value): bool => is_string($value) && $value !== ''
            ));

            if ($imageAttributes === []) {
                continue;
            }

            $modelClass::saved(function (Model $model) use ($variants, $imageAttributes): void {
                foreach ($imageAttributes as $attribute) {
                    if (! ($model->wasRecentlyCreated || $model->wasChanged($attribute))) {
                        continue;
                    }

                    $path = $model->getAttribute($attribute);
                    if (! is_string($path) || trim($path) === '') {
                        continue;
                    }

                    $variants->generateForPath($path);
                }
            });
        }
    }
}
