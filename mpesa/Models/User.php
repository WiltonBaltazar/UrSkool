<?php

namespace App\Models;

use Filament\Panel;
use App\Traits\UUID;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\HasName;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Traits\HasContentRestrictions;

class User extends Authenticatable implements HasName, MustVerifyEmail, FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, UUID, SoftDeletes, HasApiTokens, HasRoles, HasContentRestrictions;

    public function getFilamentName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'profile_photo',
        'phone_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected ?Subscription $resolvedCurrentSubscription = null;
    protected bool $hasResolvedCurrentSubscription = false;

    protected function fullName(): Attribute
    {
        return Attribute::get(
            fn() => "{$this->first_name} {$this->last_name}"
        );
    }

    /**
     * Get all subscriptions for this user
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function mobileRefreshTokens(): HasMany
    {
        return $this->hasMany(MobileRefreshToken::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Get the current active subscription (latest one)
     * This is the main method to get user's subscription
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest('created_at');
    }

    /**
     * Get the active subscription - alternative method
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->latest('created_at')
            ->first();
    }

    /**
     * Check if user has active plan access
     */
    public function hasActivePlan($planSlug = null): bool
    {
        $subscription = $this->currentSubscription();
        
        if (!$subscription) {
            return false;
        }

        if ($planSlug) {
            return $subscription->plan?->slug === $planSlug;
        }

        return true;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        if ($this->trashed() || ! $this->hasVerifiedEmail()) {
            return false;
        }

        return $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        // Be tolerant to historical role naming differences (e.g. "Super Admin").
        return $this->roles()
            ->pluck('name')
            ->contains(function (string $roleName): bool {
                $normalized = strtolower(str_replace('_', '-', trim($roleName)));
                return in_array($normalized, ['super-admin', 'super admin', 'admin'], true);
            });
    }

    /**
     * Get the current active subscription
     * This is the main method the frontend will rely on
     */
    public function currentSubscription(): ?Subscription
    {
        if ($this->hasResolvedCurrentSubscription) {
            return $this->resolvedCurrentSubscription;
        }

        $this->resolvedCurrentSubscription = $this->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            })
            ->latest('created_at')
            ->first();
        $this->hasResolvedCurrentSubscription = true;

        return $this->resolvedCurrentSubscription;
    }

    /**
     * Get the current subscription plan
     * Returns null if user has no active subscription
     */
    public function currentPlan(): ?Plan
    {
        $subscription = $this->currentSubscription();
        return $subscription ? $subscription->plan : null;
    }

    /**
     * Check if user has an active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->currentSubscription() !== null;
    }

    /**
     * Get the subscription status for display purposes
     */
    public function getSubscriptionStatus(): string
    {
        $subscription = $this->currentSubscription();

        if (!$subscription) {
            return 'inactive';
        }

        if ($subscription->isExpired()) {
            return 'expired';
        }

        return $subscription->status;
    }

    /**
     * Subscribe user to a new plan
     * This method handles the business logic of creating a new subscription
     * Ensures only one active subscription at a time
     */
    public function subscribeToPlan(Plan $plan, array $options = []): Subscription
    {
        // Cancel any existing active subscriptions
        $this->subscriptions()
            ->where('status', 'active')
            ->update([
                'status' => 'cancelled', 
                'cancelled_at' => now()
            ]);

        // Create new subscription
        return $this->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => $options['status'] ?? 'active',
            'start_date' => $options['start_date'] ?? now(),
            'end_date' => $options['end_date'] ?? null,
            'payment_status' => $options['payment_status'] ?? 'unpaid',
            'amount_paid' => $options['amount_paid'] ?? 0,
            'currency' => $options['currency'] ?? 'MZN',
            'payment_source' => $options['payment_source'] ?? 'manual',
        ]);
    }

    /**
     * Helper function to check for a specific plan
     * Updated to work with the corrected relationship
     */
    public function hasPlan(string|array $planSlugs): bool
    {
        $subscription = $this->currentSubscription();
        
        if (!$subscription) {
            return false;
        }

        if (is_string($planSlugs)) {
            $planSlugs = [$planSlugs];
        }

        // Check if the user's current plan slug is in the required list
        $userPlanSlug = $subscription->plan?->slug;

        if (!$userPlanSlug) {
            return false;
        }

        if ($userPlanSlug === 'premium' && in_array('premium', $planSlugs, true)) {
            return true;
        }

        return in_array($userPlanSlug, $planSlugs, true);
    }
}
