<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsPageView extends Model
{
    protected $table = 'analytics_page_views';

    protected $fillable = [
        'visitor_id',
        'session_id',
        'page',
        'referrer',
        'device_type',
        'country',
    ];
}
