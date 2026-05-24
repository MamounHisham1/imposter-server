<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'reward_type',
        'reward_id',
        'max_uses',
        'uses_count',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function redeemers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'promo_code_redemptions')
            ->withTimestamps();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isFullyUsed(): bool
    {
        return $this->max_uses !== null && $this->uses_count >= $this->max_uses;
    }
}
