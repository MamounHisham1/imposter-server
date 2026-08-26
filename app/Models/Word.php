<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Word extends Model
{
    protected $fillable = [
        'word_en',
        'hint_en',
        'word_ar',
        'hint_ar',
        'difficulty',
        'category',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
