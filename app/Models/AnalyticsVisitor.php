<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsVisitor extends Model
{
    protected $table = 'analytics_visitors';

    protected $fillable = [
        'visitor_id',
        'first_referrer',
        'visit_count',
        'page_view_count',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'visit_count' => 'integer',
        'page_view_count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
