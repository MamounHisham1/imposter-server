<?php

namespace App\Http\Middleware;

use App\Models\AnalyticsPageView;
use App\Models\AnalyticsVisitor;
use App\Models\AnalyticsVisitorDaily;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrackPageView
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $request->is('admin*') || $request->is('build/*') || str_starts_with($request->path(), 'favicon')) {
            return $response;
        }

        try {
            $this->recordView($request);
        } catch (\Throwable $e) {
            // Analytics should never break the app
            logger()->warning('Analytics tracking failed: '.$e->getMessage());
        }

        return $response;
    }

    private function recordView(Request $request): void
    {
        $visitorId = $_COOKIE['imp_vid'] ?? null;
        $sessionId = $_COOKIE['imp_sid'] ?? null;

        // Validate presence and format
        if (! $visitorId || ! $sessionId
            || strlen($visitorId) > 36 || strlen($sessionId) > 40
            || ! preg_match('/^[\w-]+$/', $visitorId)
            || ! preg_match('/^[\w-]+$/', $sessionId)) {
            return;
        }

        $page = '/'.$request->path();

        // Throttle: 1 page view per visitor+page per 15 seconds
        $throttleKey = "pv:{$visitorId}:{$page}";
        if (Cache::has($throttleKey)) {
            return;
        }
        Cache::put($throttleKey, true, 15);

        $referrer = $request->headers->get('referer');
        $deviceType = $this->detectDevice($request->header('User-Agent', ''));
        $today = now()->toDateString();

        // Check session state BEFORE inserting the new page view
        $sessionPageViewsToday = AnalyticsPageView::where('session_id', $sessionId)
            ->whereDate('created_at', $today)
            ->count();

        // Look up visitor BEFORE updating last_seen_at
        $visitor = AnalyticsVisitor::where('visitor_id', $visitorId)->first();
        $isNewVisitor = ! $visitor;
        $isNewSession = false;

        if ($visitor) {
            $isNewSession = ! $visitor->last_seen_at || $visitor->last_seen_at->diffInMinutes(now()) > 30;
        }

        // Now record the page view
        AnalyticsPageView::create([
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'page' => $page,
            'referrer' => $referrer ? substr($referrer, 0, 500) : null,
            'device_type' => $deviceType,
        ]);

        // Update visitor record
        if ($visitor) {
            $visitor->increment('page_view_count');
            if ($isNewSession) {
                $visitor->increment('visit_count');
            }
            $visitor->update(['last_seen_at' => now()]);
        } else {
            AnalyticsVisitor::create([
                'visitor_id' => $visitorId,
                'first_referrer' => $referrer ? substr($referrer, 0, 500) : null,
                'visit_count' => 1,
                'page_view_count' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        // Update daily aggregates
        $daily = AnalyticsVisitorDaily::getOrCreateForDate($today);
        $daily->increment('page_views');

        // Session & bounce tracking
        if ($sessionPageViewsToday === 0) {
            // First page view of this session today
            $daily->increment('sessions');
            $daily->increment('bounce_count'); // Assume bounce until 2nd page view
        } elseif ($sessionPageViewsToday === 1) {
            // Second page view — no longer a bounce
            AnalyticsVisitorDaily::where('id', $daily->id)
                ->where('bounce_count', '>', 0)
                ->decrement('bounce_count');
        }

        // Visitor type tracking
        if ($isNewVisitor) {
            $daily->increment('unique_visitors');
            $daily->increment('new_visitors');
        } elseif ($isNewSession) {
            $daily->increment('unique_visitors');
            $daily->increment('returning_visitors');
        }
    }

    private function detectDevice(string $ua): string
    {
        if (preg_match('/mobile|android.*mobile|iphone|ipod/i', $ua)) {
            return 'mobile';
        }
        if (preg_match('/tablet|ipad|android(?!.*mobile)/i', $ua)) {
            return 'tablet';
        }

        return 'desktop';
    }
}
