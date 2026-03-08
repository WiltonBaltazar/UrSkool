<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'price',
        'promo_price',
        'bg_color',
        'text_color',
        'description',
        'duration_days',
    ];

    protected $appends = [
        'effective_price',
        'formatted_price',
        'formatted_promo_price',
        'formatted_effective_price',
    ];

    protected $casts = [
        'id' => 'integer',
        'price' => 'decimal:2',
        'promo_price' => 'decimal:2',
    ];

    public function getEffectivePriceAttribute(): float
    {
        return (float) ($this->promo_price ?? $this->price);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format((float) $this->price, 2, '.', ',');
    }

    public function getFormattedPromoPriceAttribute(): ?string
    {
        if ($this->promo_price === null) {
            return null;
        }

        return number_format((float) $this->promo_price, 2, '.', ',');
    }

    public function getFormattedEffectivePriceAttribute(): string
    {
        return number_format($this->effective_price, 2, '.', ',');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // Check if the plan is valid.
    public function isValid(): bool
    {
        return $this->duration_days > 0 && (float) $this->price > 0;
    }
}
