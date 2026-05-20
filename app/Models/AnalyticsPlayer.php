<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsPlayer extends Model
{
    protected $fillable = [
        'nickname',
        'games_played',
        'games_won',
        'rounds_as_crew',
        'rounds_as_imposter',
        'wins_as_crew',
        'wins_as_imposter',
        'times_caught_as_imposter',
        'total_score',
    ];

    protected $casts = [
        'games_played' => 'integer',
        'games_won' => 'integer',
        'rounds_as_crew' => 'integer',
        'rounds_as_imposter' => 'integer',
        'wins_as_crew' => 'integer',
        'wins_as_imposter' => 'integer',
        'times_caught_as_imposter' => 'integer',
        'total_score' => 'integer',
    ];

    public static function getOrCreateForNickname(string $nickname): self
    {
        return static::firstOrCreate(
            ['nickname' => $nickname],
            array_fill_keys(
                ['games_played', 'games_won', 'rounds_as_crew', 'rounds_as_imposter',
                    'wins_as_crew', 'wins_as_imposter', 'times_caught_as_imposter', 'total_score'],
                0
            )
        );
    }
}
