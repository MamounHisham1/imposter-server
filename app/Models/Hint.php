<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hint extends Model
{
    protected $fillable = [
        'round_id',
        'player_id',
        'content',
    ];

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}
