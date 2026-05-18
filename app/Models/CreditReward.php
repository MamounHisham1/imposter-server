<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditReward extends Model
{
    protected $fillable = [
        'event',
        'credits',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
