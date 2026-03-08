<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'plan_id',
        'start_date',
        'end_date',
        'status',
        'trial_ends_at',
        'cancelled_at',
        'expiry_reminder_sent_at',
        'final_expiry_reminder_sent_at',
        'expired_notice_sent_at',
        'payment_failed_notice_sent_at',
        'payment_status',
        'amount_paid',
        'currency',
        'payment_source',
        'payment_reference',
        'mpesa_transaction_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'user_id' => 'string',
        'plan_id' => 'integer',
        'amount_paid' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'cancelled_at' => 'datetime',
        'expiry_reminder_sent_at' => 'datetime',
        'final_expiry_reminder_sent_at' => 'datetime',
        'expired_notice_sent_at' => 'datetime',
        'payment_failed_notice_sent_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if subscription is currently active
     * Active means: status is 'active' AND not expired
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // If end_date is null, it's a lifetime subscription
        if ($this->end_date === null) {
            return true;
        }

        return $this->end_date->isFuture();
    }

    /**
     * Check if the subscription has expired
     */
    public function isExpired(): bool
    {
        if ($this->end_date === null) {
            return false; // Lifetime subscriptions never expire
        }

        return $this->end_date->isPast();
    }

    /**
     * Check if subscription is in trial period
     */
    public function isTrial(): bool
    {
        if ($this->trial_ends_at === null) {
            return false;
        }

        return now()->lessThan($this->trial_ends_at);
    }

    /**
     * Get days remaining in subscription
     * Returns null for lifetime subscriptions
     */
    public function daysRemaining(): ?int
    {
        if ($this->end_date === null) {
            return null; // Lifetime subscription
        }

        $days = now()->diffInDays($this->end_date, false);
        return max(0, $days); // Don't return negative days
    }

    /**
     * Cancel the subscription
     */
    public function cancel(): bool
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return true;
    }

    /**
     * Activate the subscription
     */
    public function activate(): bool
    {
        $this->update([
            'status' => 'active',
            'cancelled_at' => null,
        ]);

        return true;
    }

    /**
     * Scope to get only active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            });
    }

    /**
     * Scope to get subscriptions for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get expired subscriptions
     */
    public function scopeExpired($query)
    {
        return $query->where('end_date', '<=', now())
            ->whereNotNull('end_date');
    }

    /**
     * Scope to get pending subscriptions (waiting for admin approval)
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if subscription is valid for access
     * This combines active status and expiration check
     */
    public function isValid(): bool
    {
        return $this->isActive() && !$this->isExpired();
    }
}
