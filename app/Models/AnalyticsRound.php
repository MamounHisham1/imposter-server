<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsRound extends Model
{
    protected $fillable = [
        'analytics_game_id',
        'round_number',
        'real_word',
        'imposter_hint',
        'winner',
        'imposter_caught',
        'player_count',
        'votes_count',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'imposter_caught' => 'boolean',
        'player_count' => 'integer',
        'votes_count' => 'integer',
    ];

    public function analyticsGame(): BelongsTo
    {
        return $this->belongsTo(AnalyticsGame::class);
    }
}
