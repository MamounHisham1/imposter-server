<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    protected $fillable = [
        'round_id',
        'voter_id',
        'target_id',
    ];

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    public function voter()
    {
        return $this->belongsTo(Player::class, 'voter_id');
    }

    public function target()
    {
        return $this->belongsTo(Player::class, 'target_id');
    }
}
