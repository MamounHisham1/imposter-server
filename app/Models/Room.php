<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Room extends Model
{
    protected $fillable = [
        'code',
        'type',
        'status',
        'max_players',
        'rounds_per_game',
        'current_round',
        'creator_id',
    ];

    protected $casts = [
        'max_players' => 'integer',
        'rounds_per_game' => 'integer',
        'current_round' => 'integer',
    ];

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
