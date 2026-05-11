<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Round extends Model
{
    protected $fillable = [
        'room_id',
        'round_number',
        'real_word',
        'imposter_hint',
    ];

    protected $casts = [
        'round_number' => 'integer',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function hints()
    {
        return $this->hasMany(Hint::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }
}
