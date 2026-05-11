<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameStat extends Model
{
    protected $fillable = [
        'nickname',
        'session_id',
        'wins_as_crew',
        'wins_as_imposter',
        'games_played',
    ];

    protected $casts = [
        'wins_as_crew' => 'integer',
        'wins_as_imposter' => 'integer',
        'games_played' => 'integer',
    ];
}
