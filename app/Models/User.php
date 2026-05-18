<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nickname',
        'email',
        'password',
        'google_id',
        'credits',
        'is_admin',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'credits' => 'integer',
            'is_admin' => 'boolean',
            'avatar' => 'array',
        ];
    }

    public function purchasedItems()
    {
        return $this->hasMany(UserPurchasedItem::class);
    }

    public function creditTransactions()
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function ownsAvatarItem(string $filename): bool
    {
        return $this->purchasedItems()
            ->where('item_type', 'element')
            ->where('item_id', $filename)
            ->exists();
    }

    public function ownsCostume(string $costumeId): bool
    {
        return $this->purchasedItems()
            ->where('item_type', 'costume')
            ->where('item_id', $costumeId)
            ->exists();
    }
}
