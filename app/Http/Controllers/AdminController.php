<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsDaily;
use App\Models\AnalyticsGame;
use App\Models\AnalyticsPageView;
use App\Models\AnalyticsPlayer;
use App\Models\AnalyticsRound;
use App\Models\AnalyticsVisitor;
use App\Models\AnalyticsVisitorDaily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Dashboard', [
            'overview' => $this->getOverview(),
            'todayStats' => $this->getTodayStats(),
            'charts' => $this->getChartData($request),
            'recentGames' => $this->getRecentGames(),
            'leaderboard' => $this->getLeaderboard(),
            'wordStats' => $this->getWordStats(),
            'hourlyActivity' => $this->getHourlyActivity(),
            'visitorOverview' => $this->getVisitorOverview(),
            'visitorCharts' => $this->getVisitorChartData($request),
            'topPages' => $this->getTopPages(),
            'topReferrers' => $this->getTopReferrers(),
            'deviceBreakdown' => $this->getDeviceBreakdown(),
        ]);
    }

    public function apiOverview()
    {
        return response()->json([
            'game' => $this->getOverview(),
            'visitor' => $this->getVisitorOverview(),
        ]);
    }

    public function apiCharts(Request $request)
    {
        return response()->json($this->getChartData($request));
    }

    public function apiVisitorCharts(Request $request)
    {
        return response()->json($this->getVisitorChartData($request));
    }

    public function apiRecentGames(Request $request)
    {
        return response()->json($this->getRecentGames($request->input('limit', 20)));
    }

    public function apiLeaderboard(Request $request)
    {
        return response()->json($this->getLeaderboard($request->input('limit', 20)));
    }

    public function apiWordStats()
    {
        return response()->json($this->getWordStats());
    }

    public function apiHourlyActivity()
    {
        return response()->json($this->getHourlyActivity());
    }

    // ── Visitor Analytics ──────────────────────────────────────

    private function getVisitorOverview(): array
    {
        $totalPageViews = AnalyticsPageView::count();
        $totalVisitors = AnalyticsVisitor::count();
        $totalSessions = AnalyticsPageView::distinct('session_id')->count('session_id');

        $totalReturning = AnalyticsVisitor::where('visit_count', '>', 1)->count();
        $totalNew = AnalyticsVisitor::where('visit_count', 1)->count();
        $totalBounces = AnalyticsVisitorDaily::sum('bounce_count');
        $totalSessionCount = AnalyticsVisitorDaily::sum('sessions');

        $todayVisitors = AnalyticsVisitorDaily::whereDate('date', now()->toDateString())->value('unique_visitors') ?? 0;
        $yesterdayVisitors = AnalyticsVisitorDaily::whereDate('date', now()->subDay()->toDateString())->value('unique_visitors') ?? 0;

        $avgPageViewsPerSession = $totalSessionCount > 0 ? round($totalPageViews / $totalSessionCount, 1) : 0;
        $bounceRate = $totalSessionCount > 0 ? round(($totalBounces / $totalSessionCount) * 100, 1) : 0;
        $returningRate = ($totalNew + $totalReturning) > 0 ? round(($totalReturning / ($totalNew + $totalReturning)) * 100, 1) : 0;

        return [
            'total_page_views' => $totalPageViews,
            'total_visitors' => $totalVisitors,
            'total_sessions' => $totalSessions,
            'total_new_visitors' => $totalNew,
            'total_returning_visitors' => $totalReturning,
            'new_visitor_rate' => ($totalNew + $totalReturning) > 0 ? round(($totalNew / ($totalNew + $totalReturning)) * 100, 1) : 0,
            'returning_rate' => $returningRate,
            'bounce_rate' => $bounceRate,
            'avg_page_views_per_session' => $avgPageViewsPerSession,
            'today_visitors' => $todayVisitors,
            'yesterday_visitors' => $yesterdayVisitors,
            'visitors_change' => $yesterdayVisitors > 0 ? round((($todayVisitors - $yesterdayVisitors) / $yesterdayVisitors) * 100, 1) : 0,
            'avg_session_duration' => $this->getAvgSessionDuration(),
        ];
    }

    private function getAvgSessionDuration(): string
    {
        $sessions = AnalyticsPageView::selectRaw('session_id, min(created_at) as first, max(created_at) as last')
            ->groupBy('session_id')
            ->havingRaw('count(*) > 1')
            ->limit(1000)
            ->get();

        if ($sessions->isEmpty()) {
            return '0m 0s';
        }

        $avgSeconds = (int) $sessions->avg(function ($s) {
            $first = strtotime($s->first);
            $last = strtotime($s->last);
            if ($first === false || $last === false) {
                return 0;
            }

            return $last - $first;
        });
        $m = intdiv($avgSeconds, 60);
        $s = $avgSeconds % 60;

        return "{$m}m {$s}s";
    }

    private function getVisitorChartData(Request $request): array
    {
        $days = $request->input('days', 30);
        $startDate = now()->subDays($days)->toDateString();

        $daily = AnalyticsVisitorDaily::whereDate('date', '>=', $startDate)
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => $row->date->toDateString());

        $dates = [];
        $pageViews = [];
        $uniqueVisitors = [];
        $newVisitors = [];
        $returningVisitors = [];
        $sessions = [];

        for ($i = $days; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $dates[] = $date;
            $row = $daily->get($date);
            $pageViews[] = $row?->page_views ?? 0;
            $uniqueVisitors[] = $row?->unique_visitors ?? 0;
            $newVisitors[] = $row?->new_visitors ?? 0;
            $returningVisitors[] = $row?->returning_visitors ?? 0;
            $sessions[] = $row?->sessions ?? 0;
        }

        return [
            'labels' => $dates,
            'page_views' => $pageViews,
            'unique_visitors' => $uniqueVisitors,
            'new_visitors' => $newVisitors,
            'returning_visitors' => $returningVisitors,
            'sessions' => $sessions,
        ];
    }

    private function getTopPages(): array
    {
        return AnalyticsPageView::selectRaw('page, count(*) as views')
            ->groupBy('page')
            ->orderByDesc('views')
            ->limit(15)
            ->get()
            ->toArray();
    }

    private function getTopReferrers(): array
    {
        return AnalyticsPageView::whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->selectRaw('referrer, count(*) as visits')
            ->groupBy('referrer')
            ->orderByDesc('visits')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'referrer' => parse_url($r->referrer, PHP_URL_HOST) ?? $r->referrer,
                'full_url' => $r->referrer,
                'visits' => $r->visits,
            ])
            ->toArray();
    }

    private function getDeviceBreakdown(): array
    {
        $devices = AnalyticsPageView::selectRaw('device_type, count(*) as count')
            ->groupBy('device_type')
            ->get()
            ->keyBy('device_type');

        $total = $devices->sum('count') ?: 1;

        return [
            ['type' => 'mobile', 'count' => $devices->get('mobile')?->count ?? 0, 'percent' => round((($devices->get('mobile')?->count ?? 0) / $total) * 100, 1)],
            ['type' => 'desktop', 'count' => $devices->get('desktop')?->count ?? 0, 'percent' => round((($devices->get('desktop')?->count ?? 0) / $total) * 100, 1)],
            ['type' => 'tablet', 'count' => $devices->get('tablet')?->count ?? 0, 'percent' => round((($devices->get('tablet')?->count ?? 0) / $total) * 100, 1)],
        ];
    }

    // ── Game Analytics (unchanged) ─────────────────────────────

    private function getOverview(): array
    {
        $totalGames = AnalyticsGame::count();
        $totalCompleted = AnalyticsGame::whereNotNull('ended_at')->count();
        $totalRounds = AnalyticsRound::count();
        $totalPlayers = AnalyticsPlayer::count();
        $totalPlayerJoins = AnalyticsDaily::sum('total_players_joined');
        $roomsCreated = AnalyticsDaily::sum('rooms_created');

        $crewWins = AnalyticsRound::where('winner', 'crew')->count();
        $imposterWins = AnalyticsRound::where('winner', 'imposter')->count();
        $ties = AnalyticsRound::where('winner', 'tie')->count();
        $totalDecisive = $crewWins + $imposterWins + $ties;

        $avgPlayers = AnalyticsGame::avg('player_count');
        $avgDuration = AnalyticsGame::whereNotNull('ended_at')->avg('duration_seconds');
        $avgRounds = AnalyticsGame::avg('rounds_played');

        return [
            'total_games' => $totalGames,
            'total_completed' => $totalCompleted,
            'total_rounds' => $totalRounds,
            'total_players' => $totalPlayers,
            'total_player_joins' => (int) $totalPlayerJoins,
            'rooms_created' => (int) $roomsCreated,
            'avg_players_per_game' => round($avgPlayers ?? 0, 1),
            'avg_duration_seconds' => round($avgDuration ?? 0),
            'avg_rounds_per_game' => round($avgRounds ?? 0, 1),
            'crew_win_rate' => $totalDecisive > 0 ? round(($crewWins / $totalDecisive) * 100, 1) : 0,
            'imposter_win_rate' => $totalDecisive > 0 ? round(($imposterWins / $totalDecisive) * 100, 1) : 0,
            'tie_rate' => $totalDecisive > 0 ? round(($ties / $totalDecisive) * 100, 1) : 0,
            'imposter_caught_rate' => $totalDecisive > 0 ? round((AnalyticsRound::where('imposter_caught', true)->count() / $totalDecisive) * 100, 1) : 0,
        ];
    }

    private function getTodayStats(): array
    {
        $today = AnalyticsDaily::whereDate('date', now()->toDateString())->first();

        if (! $today) {
            return [
                'date' => now()->toDateString(),
                'games_played' => 0,
                'games_completed' => 0,
                'rooms_created' => 0,
                'total_players_joined' => 0,
                'rounds_played' => 0,
                'crew_wins' => 0,
                'imposter_wins' => 0,
                'ties' => 0,
            ];
        }

        return $today->toArray();
    }

    private function getChartData(Request $request): array
    {
        $days = $request->input('days', 30);
        $startDate = now()->subDays($days)->toDateString();

        $daily = AnalyticsDaily::where('date', '>=', $startDate)
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => $row->date->toDateString());

        $dates = [];
        $gamesData = [];
        $playersData = [];
        $crewWinsData = [];
        $imposterWinsData = [];
        $roundsData = [];

        for ($i = $days; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $dates[] = $date;
            $row = $daily->get($date);
            $gamesData[] = $row?->games_played ?? 0;
            $playersData[] = $row?->total_players_joined ?? 0;
            $crewWinsData[] = $row?->crew_wins ?? 0;
            $imposterWinsData[] = $row?->imposter_wins ?? 0;
            $roundsData[] = $row?->rounds_played ?? 0;
        }

        return [
            'labels' => $dates,
            'games' => $gamesData,
            'players' => $playersData,
            'crew_wins' => $crewWinsData,
            'imposter_wins' => $imposterWinsData,
            'rounds' => $roundsData,
        ];
    }

    private function getRecentGames(int $limit = 20): array
    {
        return AnalyticsGame::with('rounds')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($game) => [
                'id' => $game->id,
                'room_code' => $game->room_code,
                'language' => $game->language,
                'room_type' => $game->room_type,
                'player_count' => $game->player_count,
                'rounds_played' => $game->rounds_played,
                'rounds_per_game' => $game->rounds_per_game,
                'duration_seconds' => $game->duration_seconds,
                'started_at' => $game->started_at?->toISOString(),
                'ended_at' => $game->ended_at?->toISOString(),
                'created_at' => $game->created_at->toISOString(),
                'rounds' => $game->rounds->map(fn ($round) => [
                    'round_number' => $round->round_number,
                    'real_word' => $round->real_word,
                    'winner' => $round->winner,
                    'imposter_caught' => $round->imposter_caught,
                ]),
            ])
            ->toArray();
    }

    private function getLeaderboard(int $limit = 20): array
    {
        return AnalyticsPlayer::orderByDesc('total_score')
            ->limit($limit)
            ->get()
            ->map(fn ($player) => [
                'nickname' => $player->nickname,
                'games_played' => $player->games_played,
                'games_won' => $player->games_won,
                'win_rate' => $player->games_played > 0
                    ? round(($player->games_won / $player->games_played) * 100, 1)
                    : 0,
                'rounds_as_crew' => $player->rounds_as_crew,
                'rounds_as_imposter' => $player->rounds_as_imposter,
                'wins_as_crew' => $player->wins_as_crew,
                'wins_as_imposter' => $player->wins_as_imposter,
                'times_caught_as_imposter' => $player->times_caught_as_imposter,
                'total_score' => $player->total_score,
                'avg_score_per_game' => $player->games_played > 0
                    ? round($player->total_score / $player->games_played, 1)
                    : 0,
            ])
            ->toArray();
    }

    private function getWordStats(): array
    {
        $words = AnalyticsRound::selectRaw('real_word, count(*) as times_used')
            ->groupBy('real_word')
            ->orderByDesc('times_used')
            ->limit(20)
            ->get()
            ->toArray();

        $winRates = AnalyticsRound::selectRaw('real_word,
                count(*) as total,
                sum(case when winner = \'crew\' then 1 else 0 end) as crew_wins,
                sum(case when winner = \'imposter\' then 1 else 0 end) as imposter_wins,
                sum(case when imposter_caught = 1 then 1 else 0 end) as caught_count')
            ->groupBy('real_word')
            ->havingRaw('count(*) >= 2')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($w) => [
                'word' => $w->real_word,
                'total' => $w->total,
                'crew_wins' => $w->crew_wins,
                'imposter_wins' => $w->imposter_wins,
                'caught_count' => $w->caught_count,
                'catch_rate' => $w->total > 0 ? round(($w->caught_count / $w->total) * 100, 1) : 0,
            ])
            ->toArray();

        return [
            'most_used' => $words,
            'win_rates' => $winRates,
        ];
    }

    private function getHourlyActivity(): array
    {
        $hours = array_fill(0, 24, 0);

        $driver = DB::getDriverName();
        $hourExpr = $driver === 'sqlite' ? "strftime('%H', created_at)" : 'HOUR(created_at)';

        AnalyticsGame::selectRaw("{$hourExpr} as hour, count(*) as count")
            ->groupBy('hour')
            ->get()
            ->each(function ($row) use (&$hours) {
                $hours[(int) $row->hour] = $row->count;
            });

        return [
            'labels' => array_map(fn ($h) => sprintf('%02d:00', $h), array_keys($hours)),
            'data' => array_values($hours),
        ];
    }
}
