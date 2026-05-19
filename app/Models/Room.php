<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = [
        'code',
        'type',
        'status',
        'max_players',
        'rounds_per_game',
        'language',
        'category',
        'difficulty',
        'current_round',
        'word_pool',
        'phase_votes',
        'creator_id',
        'last_activity_at',
    ];

    protected $casts = [
        'max_players' => 'integer',
        'rounds_per_game' => 'integer',
        'current_round' => 'integer',
        'word_pool' => 'array',
        'phase_votes' => 'array',
        'last_activity_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $room) {
            $room->last_activity_at = now();
        });
    }

    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function rounds()
    {
        return $this->hasMany(Round::class);
    }

    public function creator()
    {
        return $this->belongsTo(Player::class, 'creator_id');
    }

    public function touchActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Generate a unique 6-character alphanumeric room code.
     * Uses uppercase letters and digits, excluding ambiguous characters (0, O, 1, I).
     */
    public static function generateCode(): string
    {
        $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
