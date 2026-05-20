<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsGame extends Model
{
    protected $fillable = [
        'room_code',
        'language',
        'room_type',
        'player_count',
        'rounds_played',
        'rounds_per_game',
        'duration_seconds',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'player_count' => 'integer',
        'rounds_played' => 'integer',
        'rounds_per_game' => 'integer',
        'duration_seconds' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function rounds(): HasMany
    {
        return $this->hasMany(AnalyticsRound::class);
    }
}
