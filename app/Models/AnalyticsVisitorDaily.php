<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsVisitorDaily extends Model
{
    protected $table = 'analytics_visitor_daily';

    protected $fillable = [
        'date',
        'page_views',
        'unique_visitors',
        'new_visitors',
        'returning_visitors',
        'sessions',
        'bounce_count',
        'game_joins',
        'game_starts',
    ];

    protected $casts = [
        'date' => 'date',
        'page_views' => 'integer',
        'unique_visitors' => 'integer',
        'new_visitors' => 'integer',
        'returning_visitors' => 'integer',
        'sessions' => 'integer',
        'bounce_count' => 'integer',
        'game_joins' => 'integer',
        'game_starts' => 'integer',
    ];

    public static function getOrCreateForDate($date): self
    {
        $existing = static::whereDate('date', $date)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return static::create(array_merge(
                ['date' => $date],
                array_fill_keys(
                    ['page_views', 'unique_visitors', 'new_visitors', 'returning_visitors',
                        'sessions', 'bounce_count', 'game_joins', 'game_starts'],
                    0
                )
            ));
        } catch (\Exception $e) {
            return static::whereDate('date', $date)->first() ?? static::where('date', $date)->first();
        }
    }
}
