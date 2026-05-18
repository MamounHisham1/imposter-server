<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPurchasedItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
        'layer',
        'price_paid',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'price_paid' => 'integer',
            'purchased_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
