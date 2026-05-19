<?php

namespace App\Http\Controllers;

use App\Models\GameHistory;
use App\Models\GameStat;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StatsController extends Controller
{
    public function index()
    {
        // Try to identify the player: authenticated user or session nickname
        $user = Auth::user();
        $nickname = session('player_nickname');

        if (! $user && ! $nickname) {
            return redirect()->route('home');
        }

        // Build query for stats
        if ($user) {
            $stat = GameStat::where('user_id', $user->id)->first();
            $historyQuery = GameHistory::where('user_id', $user->id);
        } else {
            $stat = GameStat::where('nickname', $nickname)->first();
            $historyQuery = GameHistory::where('player_nickname', $nickname)
                ->whereNull('user_id');
        }

        // Aggregate stats
        $gamesPlayed = $stat?->games_played ?? 0;
        $winsAsCrew = $stat?->wins_as_crew ?? 0;
        $winsAsImposter = $stat?->wins_as_imposter ?? 0;
        $totalWins = $winsAsCrew + $winsAsImposter;
        $winRate = $gamesPlayed > 0 ? round(($totalWins / $gamesPlayed) * 100, 1) : 0;

        // Recent games (last 20)
        $recentGames = $historyQuery
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($game) => [
                'id' => $game->id,
                'room_code' => $game->room_code,
                'word' => $game->word,
                'role' => $game->role,
                'won' => $game->won,
                'score' => $game->score,
                'rounds_played' => $game->rounds_played,
                'date' => $game->created_at?->format('M j, Y'),
                'time' => $game->created_at?->format('g:i A'),
            ]);

        return Inertia::render('Stats', [
            'stats' => [
                'games_played' => $gamesPlayed,
                'wins_as_crew' => $winsAsCrew,
                'wins_as_imposter' => $winsAsImposter,
                'win_rate' => $winRate,
            ],
            'recent_games' => $recentGames,
            'nickname' => $user?->nickname ?? $nickname,
        ]);
    }
}
