<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameStat extends Model
{
    protected $fillable = [
        'nickname',
        'session_id',
        'user_id',
        'wins_as_crew',
        'wins_as_imposter',
        'games_played',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'wins_as_crew' => 'integer',
        'wins_as_imposter' => 'integer',
        'games_played' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
