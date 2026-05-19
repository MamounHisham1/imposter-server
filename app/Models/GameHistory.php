<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameHistory extends Model
{
    protected $fillable = [
        'room_code',
        'player_nickname',
        'user_id',
        'role',
        'word',
        'won',
        'score',
        'rounds_played',
    ];

    protected $casts = [
        'won' => 'boolean',
        'score' => 'integer',
        'rounds_played' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
