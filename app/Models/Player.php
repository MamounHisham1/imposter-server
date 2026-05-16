<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = [
        'nickname',
        'room_id',
        'is_ready',
        'is_imposter',
        'score',
        'session_id',
        'avatar',
    ];

    protected $casts = [
        'is_ready' => 'boolean',
        'is_imposter' => 'boolean',
        'score' => 'integer',
        'avatar' => 'array',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function hints()
    {
        return $this->hasMany(Hint::class);
    }

    public function votesAsVoter()
    {
        return $this->hasMany(Vote::class, 'voter_id');
    }

    public function votesAsTarget()
    {
        return $this->hasMany(Vote::class, 'target_id');
    }

    /**
     * Get the count of hints submitted by this player in the current round.
     */
    public function getHintCountAttribute(): int
    {
        $room = $this->room;

        if (! $room) {
            return 0;
        }

        $currentRound = $room->rounds()
            ->where('round_number', $room->current_round)
            ->first();

        if (! $currentRound) {
            return 0;
        }

        return $this->hints()
            ->where('round_id', $currentRound->id)
            ->count();
    }
}
