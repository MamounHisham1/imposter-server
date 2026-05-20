<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsDaily extends Model
{
    protected $table = 'analytics_daily';

    protected $fillable = [
        'date',
        'games_played',
        'games_completed',
        'rooms_created',
        'total_players_joined',
        'unique_players',
        'rounds_played',
        'crew_wins',
        'imposter_wins',
        'ties',
        'imposters_caught',
        'imposters_fled',
    ];

    protected $casts = [
        'date' => 'date',
        'games_played' => 'integer',
        'games_completed' => 'integer',
        'rooms_created' => 'integer',
        'total_players_joined' => 'integer',
        'unique_players' => 'integer',
        'rounds_played' => 'integer',
        'crew_wins' => 'integer',
        'imposter_wins' => 'integer',
        'ties' => 'integer',
        'imposters_caught' => 'integer',
        'imposters_fled' => 'integer',
    ];

    public static function getOrCreateForDate($date): self
    {
        $existing = static::whereDate('date', $date)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return static::create(array_merge(
                ['date' => $date],
                array_fill_keys(
                    ['games_played', 'games_completed', 'rooms_created', 'total_players_joined',
                        'unique_players', 'rounds_played', 'crew_wins', 'imposter_wins',
                        'ties', 'imposters_caught', 'imposters_fled'],
                    0
                )
            ));
        } catch (\Exception $e) {
            return static::whereDate('date', $date)->first() ?? static::where('date', $date)->first();
        }
    }
}
